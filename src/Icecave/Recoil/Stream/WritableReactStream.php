<?php
namespace Icecave\Recoil\Stream;

use Exception;
use Icecave\Recoil\Recoil;
use Icecave\Recoil\Stream\Exception\StreamClosedException;
use Icecave\Recoil\Stream\Exception\StreamLockedException;
use Icecave\Recoil\Stream\Exception\StreamWriteException;
use React\Stream\WritableStreamInterface as WritableReactStreamInterface;

/**
 * Exposes a ReactPHP writable stream as a Recoil writable stream.
 */
class WritableReactStream implements WritableStreamInterface
{
    /**
     * @param WritableReactStreamInterface $stream The underlying ReactPHP stream.
     */
    public function __construct(WritableReactStreamInterface $stream)
    {
        $this->stream = $stream;
        $this->locked = false;

        $this->stream->on('drain', [$this, 'onStreamDrain']);
        $this->stream->on('error', [$this, 'onStreamError']);
    }

    /**
     * [CO-ROUTINE] Write data to this stream.
     *
     * Execution of the current strand is suspended until the underlying stream
     * is drained.
     *
     * Write operations must be exclusive. If concurrent writes are attempted a
     * StreamLockedException is thrown.
     *
     * @param string       $buffer The data to write to the stream.
     * @param integer|null $length The maximum number of bytes to write.
     *
     * @return integer The number of bytes written.
     * @throws StreamClosedException if the stream is already closed.
     * @throws StreamLockedException if concurrent writes are unsupported.
     * @throws StreamWriteException  if an error occurs while writing to the stream.
     */
    public function write($buffer, $length = null)
    {
        if ($this->locked) {
            throw new StreamLockedException;
        } elseif ($this->isClosed()) {
            throw new StreamClosedException;
        }

        if (null === $length) {
            $length = strlen($buffer);
        } else {
            $buffer = substr($buffer, 0, $length);
        }

        $this->locked = true;

        yield Recoil::suspend(
            function ($strand) use ($buffer) {
                $this->strand = $strand;

                if ($this->stream->write($buffer)) {
                    $this->onStreamDrain();
                }
            }
        );

        $this->locked = false;

        yield Recoil::return_($length);
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * [CO-ROUTINE] Write all data from the given buffer to this stream.
     *
     * Execution of the current strand is suspended until the data is sent.
     *
     * Write operations must be exclusive. If concurrent writes are attempted a
     * StreamLockedException is thrown.
     *
     * @param string       $buffer The data to write to the stream.
     * @param integer|null $length The maximum number of bytes to write.
     *
     * @throws StreamClosedException if the stream is already closed.
     * @throws StreamLockedException if concurrent writes are unsupported.
     * @throws StreamWriteException  if an error occurs while writing to the stream.
     */
    public function writeAll($buffer)
    {
        return $this->write($buffer);
    }

    /**
     * [CO-ROUTINE] Close this stream.
     *
     * Closing a stream indicates that no more data will be written to the
     * stream.
     *
     * @throws StreamLockedException if a read operation is pending.
     */
    public function close()
    {
        if ($this->locked) {
            throw new StreamLockedException;
        }

        $this->locked = true;

        yield Recoil::suspend(
            function ($strand) {
                $this->stream->once(
                    'close',
                    function () use ($strand) {
                        $strand->resumeWithValue(null);
                    }
                );
                $this->stream->end();
            }
        );

        $this->locked = false;
    }

    /**
     * Check if this stream is closed.
     *
     * @return boolean True if the stream has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return !$this->stream->isWritable();
    }

    /**
     * @internal
     */
    public function onStreamDrain()
    {
        if ($this->strand) {
            $this->strand->resumeWithValue(false);
            $this->strand = null;
        }
    }

    /**
     * @internal
     */
    public function onStreamError(Exception $exception)
    {
        $this->strand->resumeWithException(new StreamWriteException($exception));
        $this->strand = null;
    }

    private $stream;
    private $locked;
    private $strand;
}

<?php

namespace Recoil\Stream;

use Exception;
use React\Stream\WritableStreamInterface;
use Recoil\Recoil;
use Recoil\Stream\Exception\StreamClosedException;
use Recoil\Stream\Exception\StreamLockedException;
use Recoil\Stream\Exception\StreamWriteException;

/**
 * Exposes a React writable stream as a Recoil writable stream.
 */
class WritableReactStream implements WritableStream
{
    /**
     * @param WritableReactStreamInterface $stream The underlying React stream.
     */
    public function __construct(WritableStreamInterface $stream)
    {
        $this->stream = $stream;

        $this->stream->on('drain', [$this, 'onStreamDrain']);
        $this->stream->on('error', [$this, 'onStreamError']);
    }

    /**
     * [COROUTINE] Write data to this stream.
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
     * @return integer               The number of bytes written.
     * @throws StreamClosedException if the stream is already closed.
     * @throws StreamLockedException if concurrent writes are unsupported.
     * @throws StreamWriteException  if an error occurs while writing to the stream.
     */
    public function write($buffer, $length = null)
    {
        if ($this->strand) {
            throw new StreamLockedException();
        } elseif ($this->isClosed()) {
            throw new StreamClosedException();
        }

        if (null === $length) {
            $length = strlen($buffer);
        } else {
            $buffer = substr($buffer, 0, $length);
        }

        yield Recoil::suspend(
            function ($strand) use ($buffer) {
                $this->strand = $strand;

                if ($this->stream->write($buffer)) {
                    $this->onStreamDrain();
                }
            }
        );

        $this->strand = null;

        yield Recoil::return_($length);
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * [COROUTINE] Write all data from the given buffer to this stream.
     *
     * Execution of the current strand is suspended until the data is sent.
     *
     * Write operations must be exclusive. If concurrent writes are attempted a
     * StreamLockedException is thrown.
     *
     * @param string $buffer The data to write to the stream.
     *
     * @throws StreamClosedException if the stream is already closed.
     * @throws StreamLockedException if concurrent writes are unsupported.
     * @throws StreamWriteException  if an error occurs while writing to the stream.
     */
    public function writeAll($buffer)
    {
        yield $this->write($buffer);
    }

    /**
     * [COROUTINE] Close this stream.
     *
     * Closing a stream indicates that no more data will be written to the
     * stream.
     */
    public function close()
    {
        if ($this->strand) {
            $this->strand->resumeWithException(new StreamClosedException());
            $this->strand = null;
            $this->stream->close();
        } else {
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
        }
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
     * @access private
     */
    public function onStreamDrain()
    {
        if ($this->strand) {
            $this->strand->resumeWithValue(false);
        }
    }

    /**
     * @access private
     */
    public function onStreamError(Exception $exception)
    {
        $this->strand->resumeWithException(new StreamWriteException($exception));
        $this->strand = null;
    }

    private $stream;
    private $strand;
}

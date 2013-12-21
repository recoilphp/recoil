<?php
namespace Icecave\Recoil\Stream;

use ErrorException;
use Icecave\Recoil\Recoil;
use Icecave\Recoil\Stream\Exception\StreamClosedException;
use Icecave\Recoil\Stream\Exception\StreamLockedException;
use Icecave\Recoil\Stream\Exception\StreamWriteException;

/**
 * A writable stream that operates directly on a native PHP stream resource.
 */
class WritableStream implements WritableStreamInterface
{
    public function __construct($stream)
    {
        $this->stream = $stream;
        $this->locked = false;
    }

    /**
     * [CO-ROUTINE] Write data to this stream.
     *
     * Execution of the current strand is suspended until the data is sent.
     *
     * Write operations must be exclusive. If concurrent writes are attempted a
     * StreamLockedException is thrown.
     *
     * @param string       $buffer The data to write to the channel.
     * @param integer|null $length The maximum number of bytes to write.
     *
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

        $this->locked = true;

        yield Recoil::suspend(
            function ($strand) {
                $strand
                    ->kernel()
                    ->eventLoop()
                    ->addWriteStream(
                        $this->stream,
                        function ($stream, $eventLoop) use ($strand) {
                            $eventLoop->removeWriteStream($this->stream);
                            $strand->resumeWithValue(null);
                        }
                    );
            }
        );

        $this->locked = false;

        if (!is_resource($this->stream)) {
            throw new StreamClosedException;
        }

        $exception = null;

        set_error_handler(
            function ($code, $message, $file, $line) use (&$exception) {
                $exception = new ErrorException($message, 0, $code, $file, $line);
            }
        );

        $bytesWritten = fwrite(
            $this->stream,
            $buffer,
            $length ?: strlen($buffer)
        );

        restore_error_handler();

        if (false === $bytesWritten) {
            throw new StreamWriteException($exception);
        }

        yield Recoil::return_($bytesWritten);
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

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
        } elseif (is_resource($this->stream)) {
            fclose($this->stream);
        }

        yield Recoil::noop();
    }

    /**
     * Check if this stream is closed.
     *
     * @return boolean True if the stream has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return !is_resource($this->stream);
    }

    private $stream;
    private $locked;
}

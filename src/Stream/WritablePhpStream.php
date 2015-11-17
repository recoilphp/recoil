<?php

namespace Recoil\Stream;

use ErrorException;
use Recoil\Recoil;
use Recoil\Stream\Exception\StreamClosedException;
use Recoil\Stream\Exception\StreamLockedException;
use Recoil\Stream\Exception\StreamWriteException;

/**
 * A writable stream that operates directly on a native PHP stream resource.
 */
class WritablePhpStream implements WritableStream
{
    /**
     * @param resource $stream The underlying PHP stream resource.
     */
    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    /**
     * [COROUTINE] Write data to this stream.
     *
     * Execution of the current strand is suspended until the data is sent.
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

        yield Recoil::suspend(
            function ($strand) {
                $this->strand = $strand;

                $strand
                    ->kernel()
                    ->eventLoop()
                    ->addWriteStream(
                        $this->stream,
                        function ($stream, $eventLoop) use ($strand) {
                            $eventLoop->removeWriteStream($this->stream);
                            $this->strand->resumeWithValue(null);
                        }
                    );
            }
        );

        $this->strand = null;

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
        while ($buffer) {
            $bytesWritten = (yield $this->write($buffer));
            $buffer       = substr($buffer, $bytesWritten);
        }
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
            $this
                ->strand
                ->kernel()
                ->eventLoop()
                ->removeWriteStream($this->stream);

            $this->strand->resumeWithException(new StreamClosedException());
            $this->strand = null;
        }

        if (is_resource($this->stream)) {
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
    private $strand;
}

<?php
namespace Icecave\Recoil\Stream;

use ErrorException;
use Icecave\Recoil\Recoil;
use Icecave\Recoil\Stream\Exception\StreamClosedException;
use Icecave\Recoil\Stream\Exception\StreamLockedException;
use Icecave\Recoil\Stream\Exception\StreamReadException;

/**
 * A readable stream that operates directly on a native PHP stream resource.
 */
class ReadableStream implements ReadableStreamInterface
{
    /**
     * @param resource $stream The underlying PHP stream resource.
     */
    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    /**
     * [CO-ROUTINE] Read data from the stream.
     *
     * Execution of the current strand is suspended until data is available or
     * the end of the data stream is reached.
     *
     * Read operations must be exclusive. If concurrent reads are attempted a
     * StreamLockedException is thrown.
     *
     * @param integer $length The maximum number of bytes to read.
     *
     * @return string                The data read from the stream.
     * @throws StreamClosedException if the stream is already closed.
     * @throws StreamLockedException if concurrent reads are unsupported.
     * @throws StreamReadException   if an error occurs while reading from the stream.
     */
    public function read($length)
    {
        if ($this->strand) {
            throw new StreamLockedException;
        } elseif ($this->isClosed()) {
            throw new StreamClosedException;
        }

        yield Recoil::suspend(
            function ($strand) {
                $this->strand = $strand;

                $strand
                    ->kernel()
                    ->eventLoop()
                    ->addReadStream(
                        $this->stream,
                        function ($stream, $eventLoop) {
                            $eventLoop->removeReadStream($stream);
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

        $buffer = fread($this->stream, $length);

        restore_error_handler();

        if (is_resource($this->stream) && feof($this->stream)) {
            fclose($this->stream);
        }

        if (false === $buffer) {
            throw new StreamReadException($exception);
        }

        yield Recoil::return_($buffer);
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * [CO-ROUTINE] Close this stream.
     *
     * Closing a stream indicates that no more data will be read from the
     * stream.
     */
    public function close()
    {
        if ($this->strand) {
            $this
                ->strand
                ->kernel()
                ->eventLoop()
                ->removeReadStream($this->stream);

            $this->strand->resumeWithException(new StreamClosedException);
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

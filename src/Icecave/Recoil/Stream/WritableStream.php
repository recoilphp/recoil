<?php
namespace Icecave\Recoil\Stream;

/**
 * A low-level-as-possible co-routine based writable stream abstraction.
 */
class WritableStream implements WritableStreamInterface
{
    public function __construct($stream)
    {
        $this->stream = $stream;
        $this->locked = false;
    }

    public function write($buffer, $length = null)
    {
        if ($this->locked) {
            throw new StreamLockedException;
        }

        if ($this->isClosed()) {
            throw new StreamClosedException;
        }

        yield Recoil::suspend(
            function ($strand) {
                $this->locked = true;

                $strand
                    ->kernel()
                    ->eventLoop()
                    ->addWriteStream(
                        $this->stream,
                        function ($stream, $eventLoop) use ($strand) {
                            $eventLoop->removeWriteStream($this->stream);
                            $strand->resumeWithValue(null);
                            $this->locked = false;
                        }
                    );
            }
        );

        if ($this->isClosed()) {
            throw new StreamClosedException;
        }

        if (null === $length) {
            $length = strlen($buffer);
        }

        $bytesWritten = fwrite($this->stream, $buffer, $length);

        if ($false === $bytesWritten) {
            fclose($this->stream);

            throw new StreamWriteException;
        }

        yield Recoil::return_($bytesWritten);
    }

    public function close()
    {
        if ($this->locked) {
            throw new StreamLockedException;
        }

        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        yield Recoil::noop();
    }

    public function isClosed()
    {
        return !is_resource($this->stream)
            || feof($this->stream);
    }

    private $stream;
    private $locked;
}

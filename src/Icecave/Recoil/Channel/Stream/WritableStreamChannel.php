<?php
namespace Icecave\Recoil\Channel\Stream;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
use Icecave\Recoil\Channel\WritableChannelInterface;
use Icecave\Recoil\Recoil;
use InvalidArgumentException;
use React\Stream\WritableStreamInterface;

/**
 * Adapts a ReactPHP writable stream into a writable channel.
 * */
class WritableStreamChannel implements WritableChannelInterface
{
    public function __construct(WritableStreamInterface $stream)
    {
        $this->stream = $stream;

        $this->stream->on('drain', [$this, 'onStreamDrain']);
        $this->stream->on('close', [$this, 'onStreamClose']);
        $this->stream->on('error', [$this, 'onStreamError']);
    }

    /**
     * [CO-ROUTINE] Write a value to this channel.
     *
     * Execution of the current strand is suspended until the inner stream has
     * been drained.
     *
     * If the channel is already closed, or is closed while a write operation is
     * pending a ChannelClosedException is thrown.
     *
     * Write operations must be exclusive. If concurrent writes are attempted
     * a ChannelLockedException is thrown.
     *
     * @param string $value The value to write to the channel.
     *
     * @throws ChannelClosedException   if the channel has been closed.
     * @throws ChannelLockedException   if concurrent writes are unsupported.
     * @throws InvalidArgumentException if $value is not a string.
     */
    public function write($value)
    {
        if ($exception = $this->exception) {
            $this->exception = null;

            throw $exception;
        } elseif ($this->isClosed()) {
            throw new ChannelClosedException($this);
        } elseif ($this->writeStrand) {
            throw new ChannelLockedException($this);
        } elseif (!is_string($value)) {
            throw new InvalidArgumentException('Value must be a string.');
        }

        yield Recoil::suspend(
            function ($strand) use ($value) {
                $this->writeStrand = $strand;

                if ($this->stream->write($value)) {
                    $this->onStreamDrain();
                }
            }
        );
    }

    /**
     * [CO-ROUTINE] Close this channel.
     *
     * Closing a channel indicates that no more values will be read from or
     * written to the channel. Any future read/write operations will fail.
     */
    public function close()
    {
        $this->stream->close();

        yield Recoil::noop();
    }

    /**
     * Check if this channel is closed.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
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
        if ($this->writeStrand) {
            $this->writeStrand->resumeWithValue(null);
            $this->writeStrand = null;
        }
    }

    /**
     * @internal
     */
    public function onStreamClose()
    {
        $this->stream->removeListener('drain', [$this, 'onStreamDrain']);
        $this->stream->removeListener('close', [$this, 'onStreamClose']);
        $this->stream->removeListener('error', [$this, 'onStreamError']);

        if ($this->writeStrand) {
            $this->writeStrand->resumeWithException(
                new ChannelClosedException($this)
            );
            $this->writeStrand = null;
        }
    }

    /**
     * @internal
     */
    public function onStreamError(Exception $exception)
    {
        if ($this->writeStrand) {
            $this->writeStrand->resumeWithException($exception);
            $this->writeStrand = null;
        } else {
            $this->exception = $exception;
        }
    }

    private $stream;
    private $writeStrand;
    private $exception;
}

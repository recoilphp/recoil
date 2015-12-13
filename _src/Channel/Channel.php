<?php

namespace Recoil\Channel;

use Recoil\Channel\Exception\ChannelClosedException;
use Recoil\Recoil;
use SplQueue;

/**
 * An unbuffered (synchronous) loop-back data channel that allows multiple
 * concurrent read/write operations.
 */
class Channel implements ReadableChannel, WritableChannel
{
    public function __construct()
    {
        $this->closed       = false;
        $this->readStrands  = new SplQueue();
        $this->writeStrands = new SplQueue();
    }

    /**
     * [COROUTINE] Read a value from this channel.
     *
     * Execution of the current strand is suspended until a value is available.
     *
     * If the channel is already closed, or is closed while a read operation is
     * pending a ChannelClosedException is thrown.
     *
     * @return mixed                  The value read from the channel.
     * @throws ChannelClosedException if the channel has been closed.
     */
    public function read()
    {
        if ($this->isClosed()) {
            throw new ChannelClosedException();
        }

        $value = (yield Recoil::suspend(
            function ($strand) {
                $this->readStrands->push($strand);

                if (!$this->writeStrands->isEmpty()) {
                    $writeStrand = $this->writeStrands->dequeue();
                    $writeStrand->resumeWithValue(null);
                }
            }
        ));

        yield Recoil::return_($value);
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * [COROUTINE] Write a value to this channel.
     *
     * Execution of the current strand is suspended until the value has been
     * consumed.
     *
     * If the channel is already closed, or is closed while a write operation is
     * pending a ChannelClosedException is thrown.
     *
     * @param mixed $value The value to write to the channel.
     *
     * @throws ChannelClosedException if the channel has been closed.
     */
    public function write($value)
    {
        if ($this->isClosed()) {
            throw new ChannelClosedException();
        }

        if ($this->readStrands->isEmpty()) {
            yield Recoil::suspend(
                [$this->writeStrands, 'push']
            );
        }

        $readStrand = $this->readStrands->dequeue();
        $readStrand->resumeWithValue($value);
    }

    /**
     * [COROUTINE] Close this channel.
     *
     * Closing a channel indicates that no more values will be read from or
     * written to the channel. Any future read/write operations will fail.
     */
    public function close()
    {
        $this->closed = true;

        while (!$this->writeStrands->isEmpty()) {
            $this
                ->writeStrands
                ->pop()
                ->resumeWithException(new ChannelClosedException());
        }

        while (!$this->readStrands->isEmpty()) {
            $this
                ->readStrands
                ->pop()
                ->resumeWithException(new ChannelClosedException());
        }

        yield Recoil::noop();
    }

    /**
     * Check if this channel is closed.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return $this->closed;
    }

    private $closed;
    private $readStrands;
    private $writeStrands;
}

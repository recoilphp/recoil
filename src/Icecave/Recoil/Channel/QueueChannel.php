<?php
namespace Icecave\Recoil\Channel;

use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Recoil;
use SplQueue;

/**
 * An unbuffered (synchronous) data channel that allows multiple concurrent
 * read/write operations.
 */
class QueueChannel implements ReadableChannelInterface, WritableChannelInterface
{
    public function __construct()
    {
        $this->closed = false;
        $this->reads  = new SplQueue;
        $this->writes = new SplQueue;
    }

    /**
     * Read a value from this channel.
     *
     * Execution of the current strand is suspended until a value is available.
     *
     * If the channel is already closed, or is closed while a read operation is
     * pending a ChannelClosedException is thrown.
     *
     * @coroutine
     *
     * @return mixed                  The value read from the channel.
     * @throws ChannelClosedException if the channel has been closed.
     */
    public function read()
    {
        yield;

        if ($this->isClosed()) {
            throw new Exception\ChannelClosedException($this);
        } elseif ($this->writes->isEmpty()) {
            $value = (yield Recoil::suspend(
                [$this->reads, 'push']
            ));
        } else {
            list($strand, $value) = $this->writes->dequeue();
            $strand->resumeWithValue(null);
        }

        yield Recoil::return_($value);
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * Write a value to this channel.
     *
     * Execution of the current strand is suspended until the value has been
     * consumed.
     *
     * If the channel is already closed, or is closed while a write operation is
     * pending a ChannelClosedException is thrown.
     *
     * @coroutine
     *
     * @param mixed $value The value to write to the channel.
     *
     * @throws ChannelClosedException if the channel has been closed.
     */
    public function write($value)
    {
        yield;

        if ($this->isClosed()) {
            throw new Exception\ChannelClosedException($this);
        } elseif ($this->reads->isEmpty()) {
            yield Recoil::suspend(
                function ($strand) use ($value) {
                    $this->writes->push([$strand, $value]);
                }
            );
        } else {
            $strand = $this->reads->dequeue();
            $strand->resumeWithValue($value);
        }
    }

    /**
     * Close this channel.
     *
     * @coroutine
     */
    public function close()
    {
        $this->closed = true;

        while (!$this->writes->isEmpty()) {
            list($strand) = $this->writes->pop();
            $strand->resumeWithException(
                new Exception\ChannelClosedException($this)
            );
        }

        while (!$this->reads->isEmpty()) {
            $strand = $this->reads->pop();
            $strand->resumeWithException(
                new Exception\ChannelClosedException($this)
            );
        }

        yield Recoil::noop();
    }

    /**
     * Check if a value can be read from the channel without blocking.
     *
     * @return boolean False if a call to read() will block; otherwise, true.
     */
    public function readyToRead()
    {
        return !$this->writes->isEmpty();
    }

    /**
     * Check if a value can be written to the channel without blocking.
     *
     * @return boolean False if a call to write() will block; otherwise, true.
     */
    public function readyForWrite()
    {
        return !$this->reads->isEmpty();
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
    private $read;
    private $writes;
}

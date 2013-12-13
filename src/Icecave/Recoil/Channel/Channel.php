<?php
namespace Icecave\Recoil\Channel;

use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
use Icecave\Recoil\Recoil;

/**
 * An unbuffered (synchronous) data channel that requires exclusive read/write
 * operations.
 */
class Channel implements ReadableChannelInterface, WritableChannelInterface
{
    public function __construct()
    {
        $this->closed = false;
    }

    /**
     * Read a value from this channel.
     *
     * Execution of the current strand is suspended until a value is available.
     *
     * If the channel is already closed, or is closed while a read operation is
     * pending a ChannelClosedException is thrown.
     *
     * Read operations must be exclusive. If concurrent reads are attempted
     * a ChannelLockedException is thrown.
     *
     * @coroutine
     *
     * @return mixed                  The value read from the channel.
     * @throws ChannelClosedException if the channel has been closed.
     * @throws ChannelLockedException if concurrent reads are attempted.
     */
    public function read()
    {
        if ($this->isClosed()) {
            throw new ChannelClosedException($this);
        } elseif ($this->readStrand) {
            throw new ChannelLockedException($this);
        }

        $value = (yield Recoil::suspend(
            function ($strand) {
                $this->readStrand = $strand;

                if ($this->writeStrand) {
                    $this->writeStrand->resumeWithValue(null);
                    $this->writeStrand = null;
                }
            }
        ));

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
     * Write operations must be exclusive. If concurrent writes are attempted
     * a ChannelLockedException is thrown.
     *
     * @coroutine
     *
     * @param mixed $value The value to write to the channel.
     *
     * @throws ChannelClosedException if the channel has been closed.
     * @throws ChannelLockedException if concurrent writes are unsupported.
     */
    public function write($value)
    {
        if ($this->isClosed()) {
            throw new ChannelClosedException($this);
        } elseif ($this->writeStrand) {
            throw new ChannelLockedException($this);
        }

        if (!$this->readStrand) {
            yield Recoil::suspend(
                function ($strand) {
                    $this->writeStrand = $strand;
                }
            );
        }

        $this->readStrand->resumeWithValue($value);
        $this->readStrand = null;
    }

    /**
     * Close this channel.
     *
     * Closing a channel indicates that no more values will be read from or
     * written to the channel. Any future read/write operations will fail.
     *
     * @coroutine
     */
    public function close()
    {
        $this->closed = true;

        if ($this->writeStrand) {
            $this->writeStrand->resumeWithException(
                new ChannelClosedException($this)
            );
            $this->writeStrand = null;
        }

        if ($this->readStrand) {
            $this->readStrand->resumeWithException(
                new ChannelClosedException($this)
            );
            $this->readStrand = null;
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
    private $readStrand;
    private $writeStrand;
}

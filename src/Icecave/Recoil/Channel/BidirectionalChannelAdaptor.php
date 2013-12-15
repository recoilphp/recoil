<?php
namespace Icecave\Recoil\Channel;

use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
use Icecave\Recoil\Recoil;

/**
 * Adapts separate read/write channels into a bidirectional channel.
 */
class BidirectionalChannelAdaptor implements BidirectionalChannelInterface
{
    public function __construct(
        ReadableChannelInterface $readChannel,
        WritableChannelInterface $writeChannel
    ) {
        $this->readChannel = $readChannel;
        $this->writeChannel = $writeChannel;
    }

    /**
     * [CO-ROUTINE] Read a value from this channel.
     *
     * The implementation MUST suspend execution of the current strand until a
     * value is available.
     *
     * If the channel is already closed, or is closed while a read operation is
     * pending the implementation MUST throw a ChannelClosedException.
     *
     * The implementation MAY require read operations to be exclusive. If
     * concurrent reads are attempted but not supported the implementation MUST
     * throw a ChannelLockedException.
     *
     * @return mixed                  The value read from the channel.
     * @throws ChannelClosedException if the channel has been closed.
     * @throws ChannelLockedException if concurrent reads are unsupported.
     */
    public function read()
    {
        return $this->readChannel->read();
    }

    /**
     * [CO-ROUTINE] Write a value to this channel.
     *
     * The implementation MUST throw an InvalidArgumentException if the type of
     * the given value is unsupported.
     *
     * The implementation MAY suspend execution of the current strand until the
     * value is consumed or internal buffers are flushed.
     *
     * If the channel is already closed, or is closed while a write operation is
     * pending the implementation MUST throw a ChannelClosedException.
     *
     * The implementation MAY require write operations to be exclusive. If
     * concurrent writes are attempted but not supported the implementation MUST
     * throw a ChannelLockedException.
     *
     * @param mixed $value The value to write to the channel.
     *
     * @throws ChannelClosedException   if the channel has been closed.
     * @throws ChannelLockedException   if concurrent writes are unsupported.
     * @throws InvalidArgumentException if the type of $value is unsupported.
     */
    public function write($value)
    {
        return $this->writeChannel->write($value);
    }

    /**
     * [CO-ROUTINE] Close this channel.
     *
     * Closing a channel indicates that no more values will be read from or
     * written to the channel. Any future read/write operations will fail.
     */
    public function close()
    {
        yield $this->readChannel->close();
        yield $this->writeChannel->close();
    }

    /**
     * Check if this channel is closed.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return $this->readChannel->isClosed()
            || $this->writeChannel->isClosed();
    }

    private $readChannel;
    private $writeChannel;
}

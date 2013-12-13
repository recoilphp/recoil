<?php
namespace Icecave\Recoil\Channel;

use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
use InvalidArgumentException;

/**
 * A data channel to which values can be written (aka consumer, sink).
 */
interface WritableChannelInterface extends ChannelInterface
{
    /**
     * Write a value to this channel.
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
     * @coroutine
     *
     * @param mixed $value The value to write to the channel.
     *
     * @throws ChannelClosedException   if the channel has been closed.
     * @throws ChannelLockedException   if concurrent writes are unsupported.
     * @throws InvalidArgumentException if the type of $value is unsupported.
     */
    public function write($value);
}

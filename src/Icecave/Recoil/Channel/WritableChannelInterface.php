<?php
namespace Icecave\Recoil\Channel;

/**
 * A data channel to which objects may be written (aka consumer, sink).
 */
interface WritableChannelInterface extends ChannelInterface
{
    /**
     * Write to this channel.
     *
     * @coroutine
     *
     * @param mixed $value The value to write to the channel.
     *
     * @throws Exception\ChannelClosedException if the channel has been closed.
     * @throws Exception\ChannelLockedException if the channel is locked.
     */
    public function write($value);
}

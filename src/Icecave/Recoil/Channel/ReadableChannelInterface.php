<?php
namespace Icecave\Recoil\Channel;

/**
 * A data channel from which objects may be obtained (aka producer, source).
 */
interface ReadableChannelInterface extends ChannelInterface
{
    /**
     * Read from this channel.
     *
     * @coroutine
     *
     * @return mixed                            The value read from the channel.
     * @throws Exception\ChannelClosedException if the channel has been closed.
     * @throws Exception\ChannelLockedException if the channel is locked.
     */
    public function read();
}

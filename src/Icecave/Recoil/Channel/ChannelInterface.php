<?php
namespace Icecave\Recoil\Channel;

/**
 * A data channel is a (possibly asynchronous) queue of objects.
 */
interface ChannelInterface
{
    /**
     * Close this channel.
     *
     * @coroutine
     */
    public function close();

    /**
     * Check if this channel is closed.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
     */
    public function isClosed();
}

<?php
namespace Icecave\Recoil\Channel;

/**
 * A data channel is primitive for sending values between strands.
 */
interface ChannelInterface
{
    /**
     * Close this channel.
     *
     * Closing a channel indicates that no more values will be read from or
     * written to the channel. Any future read/write operations will fail.
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

<?php

namespace Recoil\Channel;

use Recoil\Channel\Exception\ChannelClosedException;
use Recoil\Channel\Exception\ChannelLockedException;

/**
 * Interface and specification for coroutine based readable data-channels.
 *
 * A readable data-channel is a stream-like object that produces PHP values
 * rather than characters.
 *
 * The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
 * "SHOULD NOT", "RECOMMENDED",  "MAY", and "OPTIONAL" in this document are to
 * be interpreted as described in RFC 2119.
 *
 * @link http://www.ietf.org/rfc/rfc2119.txt
 */
interface ReadableChannel
{
    /**
     * [COROUTINE] Read a value from this channel.
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
    public function read();

    /**
     * [COROUTINE] Close this channel.
     *
     * Closing a channel indicates that no more values will be read from or
     * written to the channel. Once a channel is closed, future invocations of
     * read() must throw a ChannelClosedException.
     *
     * The implementation SHOULD NOT throw an exception if close() is called on
     * an already-closed channel.
     *
     * The implementation SHOULD support closing while a read operation is in
     * progress, otherwise ChannelLockedException MUST be thrown.
     *
     * @throws ChannelLockedException if the channel can not be closed due to a pending read operation.
     */
    public function close();

    /**
     * Check if this channel is closed.
     *
     * The implementation MUST return true after close() has been called or if
     * the end of the value stream has been reached.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
     */
    public function isClosed();
}

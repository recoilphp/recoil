<?php

namespace Recoil\Channel;

use InvalidArgumentException;
use Recoil\Channel\Exception\ChannelClosedException;
use Recoil\Channel\Exception\ChannelLockedException;

/**
 * Interface and specification for coroutine based writable data-channels.
 *
 * A writable data-channel is a stream-like object that consumes PHP values
 * rather than characters.
 *
 * The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
 * "SHOULD NOT", "RECOMMENDED",  "MAY", and "OPTIONAL" in this document are to
 * be interpreted as described in RFC 2119.
 *
 * @link http://www.ietf.org/rfc/rfc2119.txt
 */
interface WritableChannel
{
    /**
     * [COROUTINE] Write a value to this channel.
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
    public function write($value);

    /**
     * [COROUTINE] Close this channel.
     *
     * Closing a channel indicates that no more values will be written. Once a
     * channel is closed future invocations of write() MUST throw
     * a ChannelClosedException.
     *
     * The implementation SHOULD NOT throw an exception if close() is called on
     * an already-closed channel.
     *
     * The implementation SHOULD support closing while a write operation is in
     * progress, otherwise ChannelLockedException MUST be thrown.
     *
     * @throws ChannelLockedException if the channel can not be closed due to a pending write operation.
     */
    public function close();

    /**
     * Check if this channel is closed.
     *
     * The implementation MUST return true after close() has been called or the
     * channel is closed during a write operation.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
     */
    public function isClosed();
}

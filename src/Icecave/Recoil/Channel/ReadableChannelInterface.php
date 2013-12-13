<?php
namespace Icecave\Recoil\Channel;

use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;

/**
 * A data channel from which values can be read (aka producer, source).
 */
interface ReadableChannelInterface extends ChannelInterface
{
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
    public function read();
}

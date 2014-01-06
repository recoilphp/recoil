<?php
namespace Recoil\Channel;

/**
 * A data-channel that supports bidirectional communication.
 */
interface BidirectionalChannelInterface extends
    ReadableChannelInterface,
    WritableChannelInterface
{
}

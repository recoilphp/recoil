<?php
namespace Icecave\Recoil\Channel;

/**
 * A non-loop-back data channel that supports both reading and writing.
 */
interface BidirectionalChannelInterface extends
    ReadableChannelInterface,
    WritableChannelInterface
{
}

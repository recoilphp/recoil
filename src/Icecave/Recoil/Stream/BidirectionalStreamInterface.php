<?php
namespace Icecave\Recoil\Stream;

/**
 * A stream that supports bidirectional communication.
 */
interface BidirectionalStreamInterface extends
    ReadableStreamInterface,
    WritableStreamInterface
{
}

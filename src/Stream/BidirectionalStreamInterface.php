<?php
namespace Recoil\Stream;

/**
 * A stream that supports bidirectional communication.
 */
interface BidirectionalStreamInterface extends
    ReadableStreamInterface,
    WritableStreamInterface
{
}

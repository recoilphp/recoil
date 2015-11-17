<?php

namespace Recoil\Stream\Exception;

use Exception;
use LogicException;

/**
 * Indicates that a read or write operation was attempted on a stream that is
 * closed or that the stream was forcefully closed while read or write
 * operations were pending.
 */
class StreamClosedException extends LogicException
{
    public function __construct(Exception $previous = null)
    {
        parent::__construct('Stream is closed.', 0, $previous);
    }
}

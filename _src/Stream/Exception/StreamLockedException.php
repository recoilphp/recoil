<?php

namespace Recoil\Stream\Exception;

use Exception;
use LogicException;

/**
 * Indicates that multiple read or write operations were attempted on a stream
 * that does not support concurrent operations.
 */
class StreamLockedException extends LogicException
{
    public function __construct(Exception $previous = null)
    {
        parent::__construct('Stream is already in use by another strand.', 0, $previous);
    }
}

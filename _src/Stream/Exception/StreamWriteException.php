<?php

namespace Recoil\Stream\Exception;

use Exception;
use RuntimeException;

/**
 * Indicates that an error occured while attempting to write to a stream.
 */
class StreamWriteException extends RuntimeException
{
    public function __construct(Exception $previous = null)
    {
        parent::__construct('An error occurred while writing to the stream.', 0, $previous);
    }
}

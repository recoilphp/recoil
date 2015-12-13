<?php

namespace Recoil\Stream\Exception;

use Exception;
use RuntimeException;

/**
 * Indicates that an error occured while attempting to read from a stream.
 */
class StreamReadException extends RuntimeException
{
    public function __construct(Exception $previous = null)
    {
        parent::__construct('An error occurred while reading from the stream.', 0, $previous);
    }
}

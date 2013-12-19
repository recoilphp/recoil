<?php
namespace Icecave\Recoil\Stream\Exception;

use Exception;
use RuntimeException;

class StreamReadException extends RuntimeException
{
    public function __construct(Exception $previous = null)
    {
        parent::__construct('An error occurred while reading from the stream.', 0, $previous);
    }
}

<?php
namespace Icecave\Recoil\Stream\Exception;

use Exception;
use LogicException;

class StreamClosedException extends LogicException
{
    public function __construct(Exception $previous = null)
    {
        parent::__construct('Stream is closed.', 0, $previous);
    }
}

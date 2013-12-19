<?php
namespace Icecave\Recoil\Stream\Exception;

use Exception;
use LogicException;

class StreamLockedException extends LogicException
{
    public function __construct(Exception $previous = null)
    {
        parent::__construct('Stream is already in use by another strand.', 0, $previous);
    }
}

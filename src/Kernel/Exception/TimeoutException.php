<?php

namespace Recoil\Kernel\Exception;

use RuntimeException;

/**
 * Indicates that a coroutine has timed out.
 */
class TimeoutException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Execution has timed out.');
    }
}

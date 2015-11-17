<?php

namespace Recoil\Kernel\Exception;

use RuntimeException;

/**
 * Indicates that strand has been terminated.
 */
class StrandTerminatedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Execution has terminated.');
    }
}

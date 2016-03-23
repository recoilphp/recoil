<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

use RuntimeException;

/**
 * The kernel has been explicitly stopped.
 */
class KernelStoppedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The kernel has been explicitly stopped.');
    }
}

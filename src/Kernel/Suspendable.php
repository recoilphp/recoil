<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Exception;

/**
 * An object that suspends execution awaiting a result.
 */
interface Suspendable
{
    /**
     * Resume execution.
     *
     * @param mixed $result The result.
     */
    public function resume($result = null);

    /**
     * Resume execution with an exception.
     *
     * @param Exception $exception The exception.
     */
    public function throw(Exception $exception);
}

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Throwable;

interface Resumable
{
    /**
     * Resume execution.
     *
     * @param mixed $value The value to send.
     *
     * @return null
     */
    public function resume($value = null);

    /**
     * Resume execution, indicating an error state.
     *
     * @param Throwable $exception The exception describing the error.
     *
     * @return null
     */
    public function throw(Throwable $exception);
}

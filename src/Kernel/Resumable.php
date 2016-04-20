<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Throwable;

interface Resumable
{
    /**
     * Resume execution.
     *
     * @param mixed       $value  The value to send.
     * @param Strand|null $strand The strand that resumed this object, if any.
     *
     * @return null
     */
    public function resume($value = null, Strand $strand = null);

    /**
     * Resume execution, indicating an error state.
     *
     * @param Throwable   $exception The exception describing the error.
     * @param Strand|null $strand    The strand that resumed this object, if any.
     *
     * @return null
     */
    public function throw(Throwable $exception, Strand $strand = null);
}

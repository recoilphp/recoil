<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Throwable;

/**
 * An object that can be notified of the result of an operation.
 */
interface Listener
{
    /**
     * Send the result of a successful operation.
     *
     * @param mixed       $value  The operation result.
     * @param Strand|null $strand The strand that that is the source of the result, if any.
     *
     * @return null
     */
    public function send($value = null, Strand $strand = null);

    /**
     * Send the result of an un successful operation.
     *
     * @param Throwable   $exception The operation result.
     * @param Strand|null $strand    The strand that that is the source of the result, if any.
     *
     * @return null
     */
    public function throw(Throwable $exception, Strand $strand = null);
}

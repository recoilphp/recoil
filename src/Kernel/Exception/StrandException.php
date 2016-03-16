<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

use Recoil\Kernel\Strand;
use Throwable;

/**
 * A strand, or one of its observers has failed.
 */
interface StrandException extends Throwable
{
    /**
     * Strand exceptions always have a previous exception.
     *
     * @return Throwable
     */
    public function getPrevious();

    /**
     * Get the affected strand.
     */
    public function strand() : Strand;
}

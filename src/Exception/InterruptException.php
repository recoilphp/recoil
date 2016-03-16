<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Exception;

use Recoil\Kernel\Strand;
use RuntimeException;
use Throwable;

/**
 * An exception has propagated to the top of a strand's call-stack, causing it
 * to interrupt the kernel.
 *
 * This exception is not used to pass exceptions between strands.
 *
 * @see CompositeException for the exception used when concurrent strand operations fail.
 */
class InterruptException extends RuntimeException
{
    /**
     * @param Strand    $strand    The failed strand.
     * @param Throwable $exception The exception that caused the failure.
     */
    public function __construct(Strand $strand, Throwable $previous)
    {
        $this->strand = $strand;

        parent::__construct(
            'Strand #' . $strand->id() . ' failed due to an uncaught exception.',
            0,
            $previous
        );
    }

    /**
     * Get the terminated strand.
     */
    public function strand() : Strand
    {
        return $this->strand;
    }

    /**
     * @var Strand The terminated strand.
     */
    private $strand;
}

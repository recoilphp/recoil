<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

use Recoil\Kernel\Strand;
use RuntimeException;
use Throwable;

/**
 * An exception has propagated to the top of a strand's call-stack and was not
 * handled by the strand's primary listener.
 */
class StrandException extends RuntimeException
{
    /**
     * @param Strand    $strand    The failed strand.
     * @param Throwable $exception The exception that caused the failure.
     */
    public function __construct(Strand $strand, Throwable $previous)
    {
        $this->strand = $strand;

        parent::__construct(
            sprintf(
                'Unhandled exception in strand #%d: %s (%s).',
                $strand->id(),
                get_class($previous),
                $previous->getMessage()
            ),
            0,
            $previous
        );
    }

    /**
     * Get the failed strand.
     */
    public function strand() : Strand
    {
        return $this->strand;
    }

    /**
     * @var Strand The failed strand.
     */
    private $strand;
}

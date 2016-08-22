<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

use Recoil\Kernel\Strand;
use Throwable;

/**
 * A strand has caused a kernel panic.
 */
class StrandException extends KernelPanicException
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

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandObserver;
use RuntimeException;
use Throwable;

/**
 * A strand observer has thrown an exception.
 */
class StrandObserverFailedException extends RuntimeException
{
    /**
     * @param Strand         $strand    The exited strand.
     * @param StrandObserver $observer  The offending observer.
     * @param Throwable      $exception The exception thrown by the observer.
     */
    public function __construct(
        Strand $strand,
        StrandObserver $observer,
        Throwable $previous
    ) {
        $this->strand = $strand;
        $this->observer = $observer;

        parent::__construct(
            'Uncaught exception in strand observer for strand #' . $strand->id() . '.',
            0,
            $previous
        );
    }

    /**
     * Get the exited strand.
     */
    public function strand() : Strand
    {
        return $this->strand;
    }

    /**
     * Get the offending observer.
     */
    public function observer() : StrandObserver
    {
        return $this->observer;
    }

    /**
     * @var Strand The exited strand.
     */
    private $strand;

    /**
     * @var StrandObserver The offending observer.
     */
    private $observer;
}

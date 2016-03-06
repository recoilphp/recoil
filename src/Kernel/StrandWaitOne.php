<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Recoil\Exception\TerminatedException;
use Throwable;

final class StrandWaitOne implements Awaitable, StrandObserver
{
    public function __construct(Strand $substrand)
    {
        $this->substrand = $substrand;
    }

    /**
     * Perform the work.
     *
     * @param Strand $strand The strand to resume on completion.
     * @param Api    $api    The kernel API.
     */
    public function await(Strand $strand, Api $api)
    {
        $this->strand = $strand;
        $this->strand->setTerminator([$this, 'cancel']);

        $this->substrand->attachObserver($this);
    }

    /**
     * A strand completed successfully.
     *
     * @param Strand $strand The strand.
     * @param mixed  $value  The result of the strand's entry point coroutine.
     */
    public function success(Strand $strand, $value)
    {
        $this->strand->resume($value);
    }

    /**
     * A strand failed due to an uncaught exception.
     *
     * @param Strand    $strand    The strand.
     * @param Throwable $exception The exception.
     */
    public function failure(Strand $strand, Throwable $exception)
    {
        $this->strand->throw($exception);
    }

    /**
     * A strand was terminated.
     *
     * @param Strand $strand The strand.
     */
    public function terminated(Strand $strand)
    {
        $this->strand->throw(new TerminatedException($strand));
    }

    /**
     * Terminate all pending strands.
     */
    public function cancel()
    {
        $this->substrand->detachObserver($this);
        $this->substrand->terminate();
    }

    /**
     * @var Strand|null The strand to resume.
     */
    private $strand;

    /**
     * @var Strand The strand to wait for.
     */
    private $substrand;
}

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Recoil\Exception\TerminatedException;
use Throwable;

/**
 * Implementation of Api::first().
 */
final class StrandWaitFirst implements Awaitable, StrandObserver
{
    public function __construct(Strand ...$substrands)
    {
        $this->substrands = $substrands;
    }

    /**
     * Perform the work.
     *
     * @param Resumable $resumable The object to resume when the work is complete.
     * @param Api       $api       The API implementation for the current kernel.
     */
    public function await(Resumable $resumable, Api $api)
    {
        $this->resumable = $resumable;
        $this->resumable->setTerminator([$this, 'cancel']);

        foreach ($this->substrands as $substrand) {
            $substrand->setObserver($this);
        }
    }

    /**
     * A strand exited successfully.
     *
     * @param Strand $strand The strand.
     * @param mixed  $value  The result of the strand's entry point coroutine.
     */
    public function success(Strand $strand, $value)
    {
        assert(in_array($strand, $this->substrands, true), 'unknown strand');

        foreach ($this->substrands as $s) {
            if ($s !== $strand) {
                $s->setObserver(null);
                $s->terminate();
            }
        }

        $this->substrands = [];
        $this->resumable->resume($value);
    }

    /**
     * A strand exited with a failure due to an uncaught exception.
     *
     * @param Strand    $strand    The strand.
     * @param Throwable $exception The exception.
     */
    public function failure(Strand $strand, Throwable $exception)
    {
        assert(in_array($strand, $this->substrands, true), 'unknown strand');

        foreach ($this->substrands as $s) {
            if ($s !== $strand) {
                $s->setObserver(null);
                $s->terminate();
            }
        }

        $this->substrands = [];
        $this->resumable->throw($exception);
    }

    /**
     * A strand exited because it was terminated.
     *
     * @param Strand $strand The strand.
     */
    public function terminated(Strand $strand)
    {
        $this->failure($strand, new TerminatedException($strand));
    }

    /**
     * Terminate all remaining strands.
     */
    public function cancel()
    {
        foreach ($this->substrands as $strand) {
            $strand->setObserver(null);
            $strand->terminate();
        }
    }

    /**
     * @var Resumable|null The object to resume upon completion.
     */
    private $resumable;

    /**
     * @var array<Strand> The strands to wait for.
     */
    private $substrands;
}

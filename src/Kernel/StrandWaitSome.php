<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Recoil\Exception\CompositeException;
use Recoil\Exception\TerminatedException;
use Throwable;

/**
 * Implementation of Api::some().
 */
final class StrandWaitSome implements Awaitable, StrandObserver
{
    public function __construct(int $count, Strand ...$substrands)
    {
        assert($count >= 1);
        assert($count <= \count($substrands));

        $this->count = $count;
        $this->substrands = $substrands;
    }

    /**
     * Get the number of strands that must succeed before the calling strand is
     * resumed.
     *
     * The initial value is equal to the constructor's $count parameter, and is
     * decremented each time a substrand succeeds.
     *
     * @return int The number of strands that must succeed.
     */
    public function count() : int
    {
        return $this->count;
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

        $index = \array_search($strand, $this->substrands, true);
        unset($this->substrands[$index]);

        $this->values[$index] = $value;

        if (0 === --$this->count) {
            foreach ($this->substrands as $s) {
                $s->setObserver(null);
                $s->terminate();
            }

            $this->strand->resume($this->values);
        }
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

        $index = \array_search($strand, $this->substrands, true);
        unset($this->substrands[$index]);

        $this->exceptions[$index] = $exception;

        if ($this->count > count($this->substrands)) {
            foreach ($this->substrands as $s) {
                $s->setObserver(null);
                $s->terminate();
            }

            $this->strand->throw(
                new CompositeException($this->exceptions)
            );
        }
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
     * @var Strand|null The strand to resume.
     */
    private $strand;

    /**
     * @var int The number of strands that must succeed.
     */
    private $count;

    /**
     * @var array<Strand> The strands to wait for.
     */
    private $substrands;

    /**
     * @var array<integer, mixed> The results of the successful strands. Ordered
     *                     by completion order, indexed by strand order.
     */
    private $values = [];

    /**
     * @var array<integer, Exception> The exceptions thrown by failed strands.
     *                     Ordered by completion order, indexed by strand order.
     */
    private $exceptions = [];
}

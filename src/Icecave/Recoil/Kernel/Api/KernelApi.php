<?php
namespace Icecave\Recoil\Kernel\Api;

use Exception;
use Icecave\Recoil\Kernel\Strand\StrandInterface;

/**
 * The default kernel API implementation.
 */
class KernelApi implements KernelApiInterface
{
    /**
     * Get the strand the coroutine is executing on.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function strand(StrandInterface $strand)
    {
        $strand->current()->sendOnNextTick($strand);
    }

    /**
     * Get the coroutine kernel that the current strand is executing on.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function kernel(StrandInterface $strand)
    {
        $strand->current()->sendOnNextTick($strand->kernel());
    }

    /**
     * Get the React event-loop that the coroutine kernel is executing on.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function eventLoop(StrandInterface $strand)
    {
        $strand->current()->sendOnNextTick($strand->kernel()->eventLoop());
    }

    /**
     * Return a value to the calling coroutine.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to send to the calling coroutine.
     */
    public function return_(StrandInterface $strand, $value = null)
    {
        $strand->returnValue($value);
    }

    /**
     * Throw an exception to the calling coroutine.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param Exception       $exception The error to send to the calling coroutine.
     */
    public function throw_(StrandInterface $strand, Exception $exception)
    {
        $strand->throwException($exception);
    }

    /**
     * Terminate execution of the strand.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function terminate(StrandInterface $strand)
    {
        $strand->terminate();
    }

    /**
     * Suspend execution for a specified period of time.
     *
     * @param StrandInterface $strand  The currently executing strand.
     * @param number          $timeout The number of seconds to wait before resuming.
     */
    public function sleep(StrandInterface $strand, $timeout)
    {
        $strand->call(
            new Sleep($timeout)
        );
    }

    /**
     * Suspend execution of the strand.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function suspend(StrandInterface $strand, callable $callback)
    {
        $strand->suspend();

        $callback($strand);
    }

    /**
     * Execute a coroutine with a time limit.
     *
     * If the coroutine does not complete within the specified time it is
     * cancelled.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param number          $timeout   The number of seconds to wait before cancelling.
     * @param mixed           $coroutine The coroutine to execute.
     */
    public function timeout(StrandInterface $strand, $timeout, $coroutine)
    {
        $strand->call(
            new Timeout($timeout, $coroutine)
        );
    }

    /**
     * Suspend the strand until the next tick.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function cooperate(StrandInterface $strand)
    {
        $strand->suspend();

        $strand
            ->kernel()
            ->eventLoop()
            ->nextTick(
                function () use ($strand) {
                    $strand->resumeWithValue(null);
                }
            );
    }

    /**
     * Resume the strand immediately.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function noop(StrandInterface $strand)
    {
        $strand->current()->sendOnNextTick(null);
    }

    /**
     * Execute a coroutine on its own strand.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param mixed           $coroutine The coroutine to execute.
     */
    public function execute(StrandInterface $strand, $coroutine)
    {
        $substrand = $strand
            ->kernel()
            ->execute($coroutine);

        $strand->current()->sendOnNextTick($substrand);
    }

    /**
     * Stop the coroutine kernel / event-loop.
     *
     * The React event-loop can optionally be stopped when all strands have been
     * terminated.
     *
     * @param StrandInterface $strand        The currently executing strand.
     * @param boolean         $stopEventLoop Indicates whether or not the React event-loop should also be stopped.
     */
    public function stop(StrandInterface $strand, $stopEventLoop = true)
    {
        $strand->terminate();

        $strand
            ->kernel()
            ->stop($stopEventLoop);
    }

    /**
     * Wait for one or more of the given strands to exit.
     *
     * @param StrandInterface        $strand  The currently executing strand.
     * @param array<StrandInterface> $strands The strands to wait for.
     */
    public function select(StrandInterface $strand, array $strands)
    {
        $strand->call(
            new Select($strands)
        );
    }
}

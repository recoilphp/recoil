<?php

namespace Recoil\Kernel\Api;

use Exception;
use Recoil\Kernel\Strand\Strand;

/**
 * The default kernel API implementation.
 */
class StandardKernelApi implements KernelApi
{
    /**
     * Get the strand the coroutine is executing on.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function strand(Strand $strand)
    {
        $strand->resumeWithValue($strand);
    }

    /**
     * Get the coroutine kernel that the current strand is executing on.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function kernel(Strand $strand)
    {
        $strand->resumeWithValue($strand->kernel());
    }

    /**
     * Get the React event-loop that the coroutine kernel is executing on.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function eventLoop(Strand $strand)
    {
        $strand->resumeWithValue($strand->kernel()->eventLoop());
    }

    /**
     * Return a value to the calling coroutine.
     *
     * @param Strand $strand The currently executing strand.
     * @param mixed  $value  The value to send to the calling coroutine.
     */
    public function return_(Strand $strand, $value = null)
    {
        $strand->returnValue($value);
    }

    /**
     * Throw an exception to the calling coroutine.
     *
     * @param Strand    $strand    The currently executing strand.
     * @param Exception $exception The error to send to the calling coroutine.
     */
    public function throw_(Strand $strand, Exception $exception)
    {
        $strand->throwException($exception);
    }

    /**
     * Register a callback to be invoked when the current coroutine is popped
     * from the call stack.
     *
     * The callback is guaranteed to be called even if a reference to the
     * coroutine is held elsewhere (unlike a PHP finally block in a generator).
     *
     * @param Strand   $strand   The currently executing strand.
     * @param callable $callback The callback to invoke.
     */
    public function finally_(Strand $strand, callable $callback)
    {
        $strand->current()->registerFinalizeCallback($callback);

        $strand->resumeWithValue(null);
    }

    /**
     * Terminate execution of the strand.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function terminate(Strand $strand)
    {
        $strand->terminate();
    }

    /**
     * Suspend execution for a specified period of time.
     *
     * @param Strand $strand  The currently executing strand.
     * @param number $timeout The number of seconds to wait before resuming.
     */
    public function sleep(Strand $strand, $timeout)
    {
        return new Sleep($timeout);
    }

    /**
     * Suspend execution of the strand.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function suspend(Strand $strand, callable $callback = null)
    {
        $strand->suspend();

        if ($callback) {
            $callback($strand);
        }
    }

    /**
     * Execute a coroutine with a time limit.
     *
     * If the coroutine does not complete within the specified time it is
     * cancelled.
     *
     * @param Strand $strand    The currently executing strand.
     * @param number $timeout   The number of seconds to wait before cancelling.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function timeout(Strand $strand, $timeout, $coroutine)
    {
        return new Timeout($timeout, $coroutine);
    }

    /**
     * Execute the given coroutines concurrently.
     *
     * Execution of the current strand is suspended until all of the coroutines
     * are completed. If any of the coroutines fails, the remaining coroutines
     * are terminated.
     *
     * @param Strand $strand     The currently executing strand.
     * @param array  $coroutines The coroutines to execute.
     */
    public function all(Strand $strand, array $coroutines)
    {
        return new WaitAll($coroutines);
    }

    /**
     * Suspend the strand until the next tick.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function cooperate(Strand $strand)
    {
        $strand->suspend();

        $strand
            ->kernel()
            ->eventLoop()
            ->futureTick(
                function () use ($strand) {
                    $strand->resumeWithValue(null);
                }
            );
    }

    /**
     * Resume the strand immediately.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function noop(Strand $strand)
    {
        $strand->resumeWithValue(null);
    }

    /**
     * Execute a coroutine on its own strand.
     *
     * @param Strand $strand    The currently executing strand.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function execute(Strand $strand, $coroutine)
    {
        $substrand = $strand
            ->kernel()
            ->execute($coroutine);

        $strand->resumeWithValue($substrand);
    }

    /**
     * Create a function that executes a coroutine in its own strand.
     *
     * If $coroutine is callable, it is expected to return a coroutine.
     *
     * @param Strand $strand    The currently executing strand.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function callback(Strand $strand, $coroutine)
    {
        $kernel = $strand->kernel();

        if (is_callable($coroutine)) {
            $callback = function () use ($kernel, $coroutine) {
                $kernel->execute(
                    call_user_func_array(
                        $coroutine,
                        func_get_args()
                    )
                );
            };
        } else {
            $callback = function () use ($kernel, $coroutine) {
                $kernel->execute($coroutine);
            };
        }

        $strand->resumeWithValue($callback);
    }

    /**
     * Wait for one or more of the given strands to exit.
     *
     * @param Strand   $strand  The currently executing strand.
     * @param Strand[] $strands The strands to wait for.
     */
    public function select(Strand $strand, array $strands)
    {
        return new Select($strands);
    }

    /**
     * Stop the coroutine kernel / event-loop.
     *
     * The React event-loop can optionally be stopped when all strands have been
     * terminated.
     *
     * @param Strand  $strand        The currently executing strand.
     * @param boolean $stopEventLoop Indicates whether or not the React event-loop should also be stopped.
     */
    public function stop(Strand $strand, $stopEventLoop = true)
    {
        $strand->terminate();

        $strand
            ->kernel()
            ->stop($stopEventLoop);
    }
}

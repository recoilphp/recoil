<?php

namespace Recoil\Kernel\Api;

use Exception;
use Recoil\Kernel\Strand\Strand;

/**
 * Public interface for manipulating the kernel and the current strand.
 */
interface KernelApi
{
    /**
     * Get the strand the coroutine is executing on.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function strand(Strand $strand);

    /**
     * Get the coroutine kernel that the current strand is executing on.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function kernel(Strand $strand);

    /**
     * Get the React event-loop that the coroutine kernel is executing on.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function eventLoop(Strand $strand);

    /**
     * Return a value to the calling coroutine.
     *
     * @param Strand $strand The currently executing strand.
     * @param mixed  $value  The value to send to the calling coroutine.
     */
    public function return_(Strand $strand, $value = null);

    /**
     * Throw an exception to the calling coroutine.
     *
     * @param Strand    $strand    The currently executing strand.
     * @param Exception $exception The error to send to the calling coroutine.
     */
    public function throw_(Strand $strand, Exception $exception);

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
    public function finally_(Strand $strand, callable $callback);

    /**
     * Terminate execution of the strand.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function terminate(Strand $strand);

    /**
     * Suspend execution for a specified period of time.
     *
     * @param Strand $strand  The currently executing strand.
     * @param number $timeout The number of seconds to wait before resuming.
     */
    public function sleep(Strand $strand, $timeout);

    /**
     * Suspend execution of the strand until it is resumed manually.
     *
     * @param Strand        $strand   The currently executing strand.
     * @param callable|null $callback A callback which is passed the strand after it is suspended.
     */
    public function suspend(Strand $strand, callable $callback = null);

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
    public function timeout(Strand $strand, $timeout, $coroutine);

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
    public function all(Strand $strand, array $coroutines);

    /**
     * Resume the strand immediately.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function noop(Strand $strand);

    /**
     * Suspend the strand until the next tick.
     *
     * @param Strand $strand The currently executing strand.
     */
    public function cooperate(Strand $strand);

    /**
     * Execute a coroutine on its own strand.
     *
     * @param Strand $strand    The currently executing strand.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function execute(Strand $strand, $coroutine);

    /**
     * Create a function that executes a coroutine in its own strand.
     *
     * If $coroutine is callable, it is expected to return a coroutine.
     *
     * @param Strand $strand    The currently executing strand.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function callback(Strand $stand, $coroutine);

    /**
     * Wait for one or more of the given strands to exit.
     *
     * @param Strand   $strand  The currently executing strand.
     * @param Strand[] $strands The strands to wait for.
     */
    public function select(Strand $strand, array $strands);

    /**
     * Stop the coroutine kernel / event-loop.
     *
     * The React event-loop can optionally be stopped when all strands have been
     * terminated.
     *
     * @param Strand  $strand        The currently executing strand.
     * @param boolean $stopEventLoop Indicates whether or not the React event-loop should also be stopped.
     */
    public function stop(Strand $strand, $stopEventLoop = true);
}

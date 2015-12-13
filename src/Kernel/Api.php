<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

/**
 * The kernel API provides the operations available via the {@see Recoil\Recoil}
 * facade and by yielded from generator-based coroutines.
 */
interface Api
{
    /**
     * Adapt an arbitrary "task" value into work to perform and resume the
     * caller once it is complete.
     *
     * @param int         $source One of the DispatchSource constants.
     * @param Suspendable $caller The object waiting for the task to complete.
     * @param mixed       $task   The task to execute.
     * @param mixed       $key    The key yielded from the generator (for DispatchSource::COROUTINE).
     */
    public function __dispatch(
        int $source,
        Suspendable $caller,
        $task,
        $key = null
    );

    /**
     * Invoke a non-standard API operation.
     *
     * The first element in $arguments must be an instance of {@see Suspendable}.
     */
    public function __call(string $name, array $arguments);

    /**
     * Start a new strand of execution.
     *
     * This method executes a task in the "background". The caller is resumed
     * with the {@see Strand}.
     *
     * The API implementation must delay execution of the strand until the
     * next tick, allowing the caller to use Strand::capture() if necessary.
     *
     * @param Suspendable $caller The object waiting for the task to complete.
     * @param mixed       $task   The task to execute.
     */
    public function execute(Suspendable $caller, $task);

    /**
     * Create a callback function that starts a new strand of execution.
     *
     * This method can be used to integrate the kernel with callback-based
     * asynchronous code.
     *
     * The caller is resumed with the callback.
     *
     * @param Suspendable $caller The object waiting for the task to complete.
     * @param mixed       $task   The task to execute.
     */
    public function callback(Suspendable $caller, $task);

    /**
     * Allow other strands to execute then resume The object waiting for the task to complete.
     *
     * @param Suspendable $caller The object waiting for the task to complete.
     */
    public function cooperate(Suspendable $caller);

    /**
     * Resume execution of the caller after a specified interval.
     *
     * @param Suspendable $caller  The object waiting for the task to complete.
     * @param float       $seconds The interval to wait.
     */
    public function sleep(Suspendable $caller, float $seconds);

    /**
     * Execute a task with a maximum running time.
     *
     * If the task does not complete within the specified time it is cancelled,
     * otherwise the caller is resumed with the value or exception produced.
     *
     * @param Suspendable $caller  The object waiting for the task to complete.
     * @param float       $seconds The interval to allow for execution.
     * @param mixed       $task    The task to execute.
     */
    public function timeout(Suspendable $caller, float $seconds, $task);

    /**
     * Terminate the strand that the caller is running on.
     */
    public function terminate(Suspendable $caller);

    /**
     * Execute multiple tasks on their own strands and wait for them all to
     * complete.
     *
     * If one of the strands produces an exception, all pending strands are
     * terminated and the caller is resumed with that exception. Otherwise, the
     * caller is resumed with an array containing the results of each task.
     *
     * The array order matches the order of completion. The array keys indicate
     * the order in which the task was passed to this operation.
     *
     * @param Suspendable $caller    The object waiting for the task to complete.
     * @param mixed       $tasks,... The tasks to execute.
     */
    public function all(Suspendable $caller, ...$tasks);

    /**
     * Execute multiple tasks on their own strands and wait for one of them to
     * complete.
     *
     * If one of the strands completes, all pending strands are terminated and
     * the caller is resumed with the result of that strand. If all of the
     * strands produce exceptions the caller is resumed with a
     * {@see CompositeException}.
     *
     *
     * @param Suspendable $caller    The object waiting for the task to complete.
     * @param mixed       $tasks,... The tasks to execute.
     */
    public function any(Suspendable $caller, ...$tasks);

    /**
     * Execute multiple tasks on their own strands and wait for a specific
     * number of them to complete.
     *
     * Once ($count) strands have completed, all pending strands are terminated
     * and the caller is resumed with an array containing the results of the
     * completed tasks.
     *
     * The array order matches the order of completion. The array keys indicate
     * the order in which the task was passed to this operation.
     *
     * If enough strands produce an exception, such that it is no longer
     * possible for ($count) strands to complete, all pending strands are
     * terminated and the caller is resumed with a {@see CompositeException}.
     *
     * @param Suspendable $caller    The object waiting for the task to complete.
     * @param int         $count     The number of strands to wait for.
     * @param mixed       $tasks,... The tasks to execute.
     */
    public function some(Suspendable $caller, int $count, ...$tasks);

    /**
     * Execute multiple tasks in on their own strands and wait for one of them
     * to complete or produce an exception.
     *
     * The caller is resumed with the result of the first strand to finish,
     * regardless of whether it finishes successfully or produces an exception.
     *
     * @param Suspendable $caller    The object waiting for the task to complete.
     * @param mixed       $tasks,... The tasks to execute.
     */
    public function race(Suspendable $caller, ...$tasks);
}

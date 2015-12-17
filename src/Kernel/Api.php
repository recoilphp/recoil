<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Generator;
use Recoil\Exception\CompositeException;
use Recoil\Exception\TimeoutException;

interface Api
{
    /**
     * Dispatch an API call based on the key/value yielded from a coroutine.
     *
     * @param Strand $strand The strand executing the API call.
     * @param mixed  $key    The yielded key.
     * @param mixed  $value  The yielded value.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function __dispatch(Strand $strand, $key, $value);

    /**
     * Invoke a non-standard API operation.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function __call(string $name, array $arguments);

    /**
     * Start a new strand of execution.
     *
     * This method executes a coroutine in a new strand. The calling strand is
     * resumed with the new {@see Strand} object.
     *
     * The coroutine can be a generator object, or a generator function.
     *
     * The implementation must delay execution of the strand until the next
     * 'tick' of the kernel to allow the user to inspect the strand object
     * before execution begins.
     *
     * @param Strand $strand The strand executing the API call.
     * @param Generator|callable $coroutine The coroutine to execute.
     */
    public function execute(Strand $strand, $coroutine);

    /**
     * Create a callback function that starts a new strand of execution.
     *
     * This method can be used to integrate the kernel with callback-based
     * asynchronous code.
     *
     * The coroutine can be a generator object, or a generator function.
     *
     * The caller is resumed with the callback.
     *
     * @param Strand $strand The strand executing the API call.
     * @param Generator|callable $coroutine The coroutine to execute.
     */
    public function callback(Strand $strand, $coroutine);

    /**
     * Allow other strands to execute then resume the strand.
     *
     * @param Strand $strand The strand executing the API call.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function cooperate(Strand $strand);

    /**
     * Resume execution of the strand after a specified interval.
     *
     * @param Strand $strand  The strand executing the API call.
     * @param float  $seconds The interval to wait.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function sleep(Strand $strand, float $seconds);

    /**
     * Execute a task on its own strand that is terminated after a timeout.
     *
     * If the task does not complete within the specific time its strand is
     * terminated and the calling strand is resumed with a
     * {@see TimeoutException}. Otherwise, the calling strand is resumed with
     * the value or exception produced by the task.
     *
     * The task can be a generator object, a generator function, or any value
     * that can be used with __dispatch().
     *
     * @param Strand $strand  The strand executing the API call.
     * @param float  $seconds The interval to allow for execution.
     * @param mixed  $task    The task to execute.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function timeout(Strand $strand, float $seconds, $task);

    /**
     * Terminate the current strand.
     *
     * @param Strand $strand The strand executing the API call.
     */
    public function terminate(Strand $strand);

    /**
     * Execute multiple tasks on their own strands and wait for them all to
     * complete.
     *
     * If one of the strands produces an exception, all pending strands are
     * terminated and the calling strand is resumed with that exception.
     * Otherwise, the calling strand is resumed with an array containing the
     * results of each task.
     *
     * The array order matches the order of completion. The array keys indicate
     * the order in which the task was passed to this operation. This allows
     * unpacking of the result with list() to get the results in pass-order.
     *
     * Each task can be a generator object, a generator function, or any value
     * that can be used with __dispatch().
     *
     * @param Strand $strand    The strand executing the API call.
     * @param mixed  $tasks,... The tasks to execute.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function all(Strand $strand, ...$tasks);

    /**
     * Execute multiple tasks on their own strands and wait for one of them to
     * complete.
     *
     * If one of the strands completes, all pending strands are terminated and
     * the calling strand is resumed with the result of that strand. If all of
     * the strands produce exceptions the calling strand is resumed with a
     * {@see CompositeException}.
     *
     * Each task can be a generator object, a generator function, or any value
     * that can be used with __dispatch().
     *
     * @param Strand $strand    The strand executing the API call.
     * @param mixed  $tasks,... The tasks to execute.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function any(Strand $strand, ...$tasks);

    /**
     * Execute multiple tasks on their own strands and wait for a specific
     * number of them to complete.
     *
     * Once ($count) strands have completed, all pending strands are terminated
     * and the calling strand is resumed with an array containing the results of
     * the completed tasks.
     *
     * The array order matches the order of completion. The array keys indicate
     * the order in which the task was passed to this operation. This allows
     * unpacking of the result with list() to get the results in pass-order.
     *
     * If enough strands produce an exception, such that it is no longer
     * possible for ($count) strands to complete, all pending strands are
     * terminated and the calling strand is resumed with a
     * {@see CompositeException}.
     *
     * @param Strand $strand    The strand executing the API call.
     * @param int    $count     The number of strands to wait for.
     * @param mixed  $tasks,... The tasks to execute.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function some(Strand $strand, int $count, ...$tasks);

    /**
     * Execute multiple tasks in on their own strands and wait for one of them
     * to complete or produce an exception.
     *
     * The calling strand resumed with the result of the first strand to finish,
     * regardless of whether it finishes successfully or produces an exception.
     *
     * @param Strand $strand    The strand executing the API call.
     * @param mixed  $tasks,... The tasks to execute.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function race(Strand $strand, ...$tasks);
}

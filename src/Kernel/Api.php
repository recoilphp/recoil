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
     */
    public function __dispatch(Strand $strand, $key, $value);

    /**
     * Invoke a non-standard API operation.
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
     * @param Strand $strand    The strand executing the API call.
     * @param mixed  $coroutine The coroutine to execute.
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
     * @param Strand $strand    The strand executing the API call.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function callback(Strand $strand, $coroutine);

    /**
     * Allow other strands to execute then resume the strand.
     *
     * @param Strand $strand The strand executing the API call.
     */
    public function cooperate(Strand $strand);

    /**
     * Resume execution of the strand after a specified interval.
     *
     * @param Strand $strand  The strand executing the API call.
     * @param float  $seconds The interval to wait.
     */
    public function sleep(Strand $strand, float $seconds);

    /**
     * Execute a coroutine on its own strand that is terminated after a timeout.
     *
     * If the coroutine does not complete within the specific time its strand is
     * terminated and the calling strand is resumed with a {@see TimeoutException}.
     * Otherwise, the calling strand is resumed with the value or exception
     * produced by the coroutine.
     *
     * @param Strand $strand    The strand executing the API call.
     * @param float  $seconds   The interval to allow for execution.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function timeout(Strand $strand, float $seconds, $coroutine);

    /**
     * Suspend execution of the current strand.
     *
     * @param Strand        $strand The strand executing the API call.
     * @param callable|null $fn     A function invoked with the strand after it is suspended.
     */
    public function suspend(Strand $strand, callable $fn = null);

    /**
     * Terminate the current strand.
     *
     * @param Strand $strand The strand executing the API call.
     */
    public function terminate(Strand $strand);

    /**
     * Execute multiple coroutines on their own strands and wait for them all to
     * complete.
     *
     * If one of the strands produces an exception, all pending strands are
     * terminated and the calling strand is resumed with that exception.
     * Otherwise, the calling strand is resumed with an array containing the
     * results of each coroutine.
     *
     * The array order matches the order of completion. The array keys indicate
     * the order in which the coroutine was passed to this operation. This
     * allows unpacking of the result with list() to get the results in
     * pass-order.
     *
     * @param Strand $strand         The strand executing the API call.
     * @param mixed  $coroutines,... The coroutines to execute.
     */
    public function all(Strand $strand, ...$coroutines);

    /**
     * Execute multiple coroutines on their own strands and wait for one of them
     * to complete.
     *
     * If one of the strands completes, all pending strands are terminated and
     * the calling strand is resumed with the result of that strand. If all of
     * the strands produce exceptions the calling strand is resumed with a
     * {@see CompositeException}.
     *
     * @param Strand $strand         The strand executing the API call.
     * @param mixed  $coroutines,... The coroutines to execute.
     */
    public function any(Strand $strand, ...$coroutines);

    /**
     * Execute multiple coroutines on their own strands and wait for a specific
     * number of them to complete.
     *
     * Once ($count) strands have completed, all pending strands are terminated
     * and the calling strand is resumed with an array containing the results of
     * the completed coroutines.
     *
     * The array order matches the order of completion. The array keys indicate
     * the order in which the coroutine was passed to this operation.
     *
     * If enough strands produce an exception, such that it is no longer
     * possible for ($count) strands to complete, all pending strands are
     * terminated and the calling strand is resumed with a
     * {@see CompositeException}.
     *
     * If ($count) is less than one, or greater than the number of provided
     * coroutines, the strand is resumed with an {@see InvalidArgumentException}.
     *
     * @param Strand $strand         The strand executing the API call.
     * @param int    $count          The number of strands to wait for.
     * @param mixed  $coroutines,... The coroutines to execute.
     */
    public function some(Strand $strand, int $count, ...$coroutines);

    /**
     * Execute multiple coroutines in on their own strands and wait for one of
     * them to complete or produce an exception.
     *
     * The calling strand resumed with the result of the first strand to finish,
     * regardless of whether it finishes successfully or produces an exception.
     *
     * @param Strand $strand         The strand executing the API call.
     * @param mixed  $coroutines,... The coroutines to execute.
     */
    public function first(Strand $strand, ...$coroutines);
}

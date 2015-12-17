<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Generator;
use Recoil\Exception\CompositeException;
use Recoil\Exception\RejectedException;
use Recoil\Exception\TimeoutException;
use Throwable;

trait ApiTrait
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
    public function __dispatch(Strand $strand, $key, $value)
    {
        if (null === $value) {
            return $this->cooperate($strand);
        } elseif (\is_integer($value) || \is_float($value)) {
            return $this->sleep($strand, $value);
        } elseif (\is_array($value)) {
            return $this->all($strand, ...$value);
        } elseif (\method_exists($value, 'then')) {
            $value->then(
                static function ($result) use ($strand) {
                    $strand->resume($result);
                },
                static function ($reason) use ($strand) {
                    if ($reason instanceof Throwable) {
                        $strand->throw($reason);
                    } else {
                        $strand->throw(new RejectedException($reason));
                    }
                }
            );

            if (\method_exists($value, 'cancel')) {
                return static function () use ($value) {
                    $value->cancel();
                };
            }

            return null;
        }

        $strand->throw(new UnexpectedValueException(
            'The yielded pair ('
            . Repr::repr($key)
            . ', '
            . Repr::repr($value)
            . ') does not describe any known operation.'
        ));
    }

    /**
     * Invoke a non-standard API operation.
     */
    public function __call(string $name, array $arguments)
    {
        assert(($arguments[0] ?? null) instanceof Strand);

        $arguments[0]->throw(new BadMethodCallException(
            'The API does not implement an operation named "' . $name . '".'
        ));
    }

    /**
     * Terminate the current strand.
     *
     * @param Strand $strand The strand executing the API call.
     */
    public function terminate(Strand $strand)
    {
        $strand->terminate();
    }

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
     */
    public function all(Strand $strand, ...$tasks)
    {
        // @todo
    }

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
     */
    public function any(Strand $strand, ...$tasks)
    {
        // @todo
    }

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
     */
    public function some(Strand $strand, int $count, ...$tasks)
    {
        // @todo
    }

    /**
     * Execute multiple tasks in on their own strands and wait for one of them
     * to complete or produce an exception.
     *
     * The calling strand resumed with the result of the first strand to finish,
     * regardless of whether it finishes successfully or produces an exception.
     *
     * @param Strand $strand    The strand executing the API call.
     * @param mixed  $tasks,... The tasks to execute.
     */
    public function race(Strand $strand, ...$tasks)
    {
        // @todo
    }
}

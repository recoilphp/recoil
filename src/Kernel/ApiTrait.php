<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use BadMethodCallException;
use Exception;
use Generator;
use Icecave\Repr\Repr;
use Recoil\Exception\RejectedException;
use UnexpectedValueException;

/**
 * Provides the default API implementation, where possible.
 */
trait ApiTrait
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
    ) {
        assert($source >= DispatchSource::KERNEL && $source <= DispatchSource::COROUTINE);

        $depth = 0;
        nested:
        $depth++;

        if ($task instanceof AwaitableProvider) {
            $task->awaitable()->await($caller, $this);
        } elseif ($task instanceof Awaitable) {
            $task->await($caller, $this);
        } elseif ($task instanceof Generator) {
            (new GeneratorCoroutine($task))->await($caller, $this);
        } elseif ($depth > 1) {
            $caller->resume($task);
        } elseif (\is_callable($task)) {
            try {
                $task = $task();
                goto nested;
            } catch (Exception $e) {
                $caller->throw($e);
            }
        } elseif (null === $task) {
            $this->cooperate($caller);
        } elseif (\is_integer($task) || \is_float($task)) {
            $this->sleep($caller, $task);
        } elseif (\is_array($task)) {
            $this->all($caller, ...$task);
        } elseif (\method_exists($task, 'then')) {
            $task->then(
                [$caller, 'resume'],
                function ($reason) use ($caller) {
                    $caller->throw(
                        $reason instanceof Exception
                            ? $reason
                            : new RejectedException($reason)
                    );
                }
            );

            if (\method_exists($task, 'cancel')) {
                // @todo
            }
        } elseif (DispatchSource::COROUTINE === $source) {
            $caller->throw(new UnexpectedValueException(
                'The yielded pair ('
                . Repr::repr($key)
                . ', '
                . Repr::repr($task)
                . ') does not describe any known operation.'
            ));
        } else {
            $caller->throw(new UnexpectedValueException(
                'The value ('
                . Repr::repr($task)
                . ') does not describe any known operation.'
            ));
        }
    }

    /**
     * Invoke a non-standard API operation.
     *
     * The first element in $arguments must be an instance of {@see Suspendable}.
     */
    public function __call(string $name, array $arguments)
    {
        $this->unknownOperation($name, ...$arguments);
    }

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
    public function callback(Suspendable $caller, $task)
    {
        $caller->resume(
            function () use ($caller, $task) {
                return $this->__dispatch(
                    DispatchSource::API,
                    $caller,
                    $task
                );
            }
        );
    }

    /**
     * Terminate the strand that the caller is running on.
     */
    public function terminate(Suspendable $caller)
    {
        $caller->throw(new \LogicException('Not implemented'));
    }

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
    public function all(Suspendable $caller, ...$tasks)
    {
        // @todo
    }

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
    public function any(Suspendable $caller, ...$tasks)
    {
        // @todo
    }

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
    public function some(Suspendable $caller, int $count, ...$tasks)
    {
        // @todo
    }

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
    public function race(Suspendable $caller, ...$tasks)
    {
        // @todo
    }

    private function unknownOperation(string $name, Suspendable $caller)
    {
        $caller->throw(new BadMethodCallException(
            'The API does not implement an operation named "' . $name . '".'
        ));
    }
}

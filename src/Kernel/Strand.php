<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Throwable;

interface Strand // @todo implements AwaitableProvider
{
    /**
     * Get the strand's ID.
     *
     * No two active on the same kernel may share an ID.
     *
     * @return int The strand ID.
     */
    public function id() : int;

    /**
     * @return Kernel The kernel on which the strand is executing.
     */
    public function kernel();

    /**
     * Add a strand observer.
     *
     * @param StrandObserver $observer
     */
    public function attachObserver(StrandObserver $observer);

    /**
     * Remove a strand observer.
     *
     * @param StrandObserver $observer
     */
    public function detachObserver(StrandObserver $observer);

    /**
     * Start the strand.
     *
     * @param mixed $coroutine The strand's entry-point.
     */
    public function start($coroutine);

    /**
     * Terminate execution of the strand.
     *
     * If the strand is suspended waiting on an asynchronous operation, that
     * operation is cancelled.
     *
     * The call stack is not unwound, it is simply discarded.
     */
    public function terminate();

    /**
     * Resume execution of a suspended strand.
     *
     * @param mixed $value The value to send to the coroutine on the the top of the call stack.
     */
    public function resume($value = null);

    /**
     * Resume execution of a suspended strand with an error.
     *
     * @param Throwable $exception The exception to send to the coroutine on the top of the call stack.
     */
    public function throw(Throwable $exception);

    /**
     * Set the strand 'terminator'.
     *
     * The terminator is a function invoked when the strand is terminated. It is
     * used by the kernel API to clean up and pending asynchronous operations.
     *
     * The terminator function is removed without being invoked when the strand
     * is resumed.
     *
     * @param callable|null $fn The terminator function.
     */
    public function setTerminator(callable $fn = null);
}

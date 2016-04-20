<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Throwable;

interface Strand extends Resumable, AwaitableProvider
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
     * Start the strand.
     *
     * @return null
     */
    public function start();

    /**
     * Terminate execution of the strand.
     *
     * If the strand is suspended waiting on an asynchronous operation, that
     * operation is cancelled.
     *
     * The call stack is not unwound, it is simply discarded.
     *
     * @return null
     */
    public function terminate();

    /**
     * Resume execution of a suspended strand.
     *
     * @param mixed $value The value to send to the coroutine on the the top of the call stack.
     *
     * @return null
     */
    public function resume($value = null);

    /**
     * Resume execution of a suspended strand with an error.
     *
     * @param Throwable $exception The exception to send to the coroutine on the top of the call stack.
     *
     * @return null
     */
    public function throw(Throwable $exception);

    /**
     * Check if the strand has exited.
     */
    public function hasExited() : bool;

    /**
     * Set the strand observer.
     *
     * @return null
     */
    public function setObserver(StrandObserver $observer = null);

    /**
     * Set the strand 'terminator'.
     *
     * The terminator is a function invoked when the strand is terminated. It is
     * used by the kernel API to clean up any pending asynchronous operations.
     *
     * The terminator function is removed without being invoked when the strand
     * is resumed.
     *
     * @return null
     */
    public function setTerminator(callable $fn = null);
}

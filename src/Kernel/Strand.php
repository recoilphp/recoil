<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Recoil\Kernel\Exception\PrimaryListenerRemovedException;
use Throwable;

interface Strand extends Listener, AwaitableProvider
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
    public function kernel() : Kernel;

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
     * The call-stack is not unwound, it is simply discarded.
     *
     * @return null
     */
    public function terminate();

    /**
     * Resume execution of a suspended strand.
     *
     * @param mixed       $value  The value to send to the coroutine on the the top of the call-stack.
     * @param Strand|null $strand The strand that resumed this one, if any.
     *
     * @return null
     */
    public function send($value = null, Strand $strand = null);

    /**
     * Resume execution of a suspended strand with an error.
     *
     * @param Throwable   $exception The exception to send to the coroutine on the top of the call-stack.
     * @param Strand|null $strand    The strand that resumed this one, if any.
     *
     * @return null
     */
    public function throw(Throwable $exception, Strand $strand = null);

    /**
     * Check if the strand has exited.
     */
    public function hasExited() : bool;

    /**
     * Set the primary listener.
     *
     * If the current primary listener is not the kernel, it is notified with
     * a {@see PrimaryListenerRemovedException}.
     *
     * @return null
     */
    public function setPrimaryListener(Listener $listener);

    /**
     * Set the primary listener to the kernel.
     *
     * The current primary listener is not notified.
     *
     * @return null
     */
    public function clearPrimaryListener();

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

    /**
     * Create a uni-directional link to another strand.
     *
     * If this strand exits, any linked strands are terminated.
     *
     * @return null
     */
    public function link(Strand $strand);

    /**
     * Break a previously created uni-directional link to another strand.
     *
     * @return null
     */
    public function unlink(Strand $strand);

    /**
     * Get the current trace for this strand.
     *
     * @return StrandTrace|null
     */
    public function trace();

    /**
     * Set the current trace for this strand.
     *
     * This method has no effect when assertions are disabled.
     *
     * @return null
     */
    public function setTrace(StrandTrace $trace = null);
}

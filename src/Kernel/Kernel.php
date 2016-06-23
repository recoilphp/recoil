<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Recoil\Kernel\Exception\StrandException;
use Throwable;

interface Kernel extends Listener
{
    /**
     * Execute a coroutine on a new strand.
     *
     * Execution is deferred until control returns to the kernel. This allows
     * the caller to manipulate the returned {@see Strand} object before
     * execution begins.
     *
     * @param mixed $coroutine The strand's entry-point.
     */
    public function execute($coroutine) : Strand;

    /**
     * Run the kernel until all strands exit or the kernel is stopped.
     *
     * Calls to wait(), {@see Kernel::waitForStrand()} and {@see Kernel::waitFor()}
     * may be nested. This can be useful within synchronous code to block
     * execution until a particular asynchronous operation is complete. Care
     * must be taken to avoid deadlocks.
     *
     * @return bool            False if the kernel was stopped with {@see Kernel::stop()}; otherwise, true.
     * @throws StrandException A strand failure was not handled by the exception handler.
     */
    public function wait() : bool;

    /**
     * Run the kernel until a specific strand exits or the kernel is stopped.
     *
     * Calls to {@see Kernel::wait()}, waitForStrand() and {@see Kernel::waitFor()}
     * may be nested. This can be useful within synchronous code to block
     * execution until a particular asynchronous operation is complete. Care
     * must be taken to avoid deadlocks.
     *
     * @param Strand $strand The strand to wait for.
     *
     * @return mixed                  The strand result, on success.
     * @throws Throwable              The exception thrown by the strand, if failed.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException Execution was stopped with {@see Kernel::stop()}.
     * @throws StrandException        A strand failure was not handled by the exception handler.
     */
    public function waitForStrand(Strand $strand);

    /**
     * Run the kernel until the given coroutine returns or the kernel is stopped.
     *
     * This is a convenience method equivalent to:
     *
     *      $strand = $kernel->execute($coroutine);
     *      $kernel->waitForStrand($strand);
     *
     * Calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()} and waitFor()
     * may be nested. This can be useful within synchronous code to block
     * execution until a particular asynchronous operation is complete. Care
     * must be taken to avoid deadlocks.
     *
     * @param mixed $coroutine The coroutine to execute.
     *
     * @return mixed                  The return value of the coroutine.
     * @throws Throwable              The exception produced by the coroutine, if any.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException Execution was stopped with {@see Kernel::stop()}.
     * @throws StrandException        A strand failure was not handled by the exception handler.
     */
    public function waitFor($coroutine);

    /**
     * Stop the kernel.
     *
     * All nested calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()}
     * or {@see Kernel::waitFor()} are stopped.
     *
     * wait() returns false when the kernel is stopped, the other variants throw
     * a {@see KernelStoppedException}.
     *
     * @return null
     */
    public function stop();

    /**
     * Set the exception handler.
     *
     * The exception handler is invoked whenever an exception propagates to the
     * top of a strand's call-stack, or when a strand's primary listener throws
     * an exception.
     *
     * The exception handler function must accept a single parameter of type
     * {@see StrandException} and return a boolean indicating whether or not the
     * exception was handled.
     *
     * If the exception handler returns false, or is not set (the default), the
     * exception will be thrown by the outer-most call to {@see Kernel::wait()},
     * {@see Kernel::waitForStrand()} or {@see Kernel::waitFor()}, after which
     * the kernel may not be restarted.
     *
     * @param callable|null $fn The exception handler (null = remove).
     *
     * @return null
     */
    public function setExceptionHandler(callable $fn = null);
}

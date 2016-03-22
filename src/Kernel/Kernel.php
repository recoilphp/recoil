<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Throwable;

interface Kernel
{
    /**
     * Execute a coroutine on a new strand.
     *
     * Execution is deferred until control returns to the kernel. This allows
     * the caller to manipulate the returned {@see Strand} object before
     * execution begins.
     *
     * @see Api::execute() to start a new strand from within a coroutine.
     *
     * @param mixed $coroutine The strand's entry-point.
     */
    public function execute($coroutine) : Strand;

    /**
     * Run the kernel until all strands exit or the kernel is stopped.
     *
     * Calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()} and
     * {@see Kernel::waitFor()} may be nested. This can be useful within
     * synchronous code to block execution until a particular asynchronous
     * operation is complete. Care must be taken to avoid deadlocks.
     *
     * @see Kernel::waitForStrand() to wait for a specific strand.
     * @see Kernel::waitFor() to wait for a specific awaitable.
     * @see Kernel::stop() to stop the kernel.
     * @see Kernel::setExceptionHandler() to control how strand failures are handled.
     *
     * @return bool            False if the kernel was stopped with {@see Kernel::stop()}; otherwise, true.
     * @throws StrandException A strand or strand observer has failed when thre is no exception handler.
     */
    public function wait() : bool;

    /**
     * Run the kernel until a specific strand exits or the kernel is stopped.
     *
     * Calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()} and
     * {@see Kernel::waitFor()} may be nested. This can be useful within
     * synchronous code to block execution until a particular asynchronous
     * operation is complete. Care must be taken to avoid deadlocks.
     *
     * @see Kernel::wait() to wait for all strands.
     * @see Kernel::waitFor() to wait for a specific awaitable.
     * @see Kernel::stop() to stop the kernel.
     *
     * @param Strand $strand The strand to wait for.
     *
     * @return mixed                  The strand result, on success.
     * @throws Throwable              The exception thrown by the strand, if failed.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException Execution was stopped with {@see Kernel::stop()}.
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
     * Calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()} and
     * {@see Kernel::waitFor()} may be nested. This can be useful within
     * synchronous code to block execution until a particular asynchronous
     * operation is complete. Care must be taken to avoid deadlocks.
     *
     * @see Kernel::execute() to start a new strand.
     * @see Kernel::waitForStrand() to wait for a specific strand.
     * @see Kernel::wait() to wait for all strands.
     * @see Kernel::stop() to stop the kernel.
     *
     * @param mixed $coroutine The coroutine to execute.
     *
     * @return mixed                  The return value of the coroutine.
     * @throws Throwable              The exception produced by the coroutine, if any.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException Execution was stopped with {@see Kernel::stop()}.
     */
    public function waitFor($coroutine);

    /**
     * Stop the kernel.
     *
     * The outer-most call to {@see Kernel::wait()}, {@see Kernel::waitForStrand()}
     * or {@see Kernel::waitFor()} is stopped.
     *
     * {@see Kernel::wait()} returns false when the kernel is stopped, the other
     * variants throw a {@see KernelStoppedException}.
     *
     * @return null
     */
    public function stop();

    /**
     * Set the exception handler.
     *
     * The exception handler is invoked whenever a strand fails. That is, when
     * an exception is allowed to propagate to the top of the strand's
     * call-stack. Or, when a strand observer throws an exception.
     *
     * The exception handler function must accept a single parameter of type
     * {@see StrandException}.
     *
     * By default, or if the exception handler is explicitly set to NULL, the
     * exception will instead be thrown by the outer-most call to {@see Kernel::wait()},
     * {@see Kernel::waitForStrand()} or {@see Kernel::waitFor()}, after which
     * the kernel may not be restarted.
     *
     * @param callable|null $fn The error handler (null = remove).
     *
     * @return null
     */
    public function setExceptionHandler(callable $fn = null);
}

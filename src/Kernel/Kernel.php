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
     * @return null
     * @throws StrandException One or more strands produced unhandled exceptions.
     */
    public function wait();

    /**
     * Run the kernel until a specific strand exits or the kernel is stopped.
     *
     * If the strand fails, its exception is NOT passed to the kernel's
     * exception handler, instead it is re-thrown by this method.
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
     * @throws KernelStoppedException Execution was stopped with {@see Kernel::stop()} before the strand exited.
     * @throws StrandException        One or more other strands produced unhandled exceptions.
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
     * If the strand fails, its exception is NOT passed to the kernel's
     * exception handler, instead it is re-thrown by this method.
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
     * @throws StrandException        One or more other strands produced unhandled exceptions.
     */
    public function waitFor($coroutine);

    /**
     * Stop the kernel.
     *
     * The kernel can not be restarted until the outer-most call to {@see Kernel::wait()},
     * {@see Kernel::waitForStrand()} and {@see Kernel::waitFor()} has returned.
     *
     * @return null
     */
    public function stop();

    /**
     * Set a user-defined exception handler function.
     *
     * The exception handler function is invoked when a strand exits with an
     * unhandled failure. That is, whenever an exception propagates to the top
     * of the strand's call-stack and the strand does not already have a
     * mechanism in place to deal with the exception.
     *
     * The exception handler function must have the following signature:
     *
     *      function (Strand $strand, Throwable $exception)
     *
     * The first parameter is the strand that produced the exception, the second
     * is the exception itself.
     *
     * The handler may re-throw the exception to indicate that it cannot be
     * handled. In this case (or when there is no exception handler) a {@see StrandException}
     * is thrown by all nested calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()}
     * or {@see Kernel::waitFor()}.
     *
     * The kernel can not be restarted until the outer-most call to {@see Kernel::wait()},
     * {@see Kernel::waitForStrand()} and {@see Kernel::waitFor()} has thrown.
     *
     * @param callable|null $fn The exception handler (null = remove).
     *
     * @return null
     */
    public function setExceptionHandler(callable $fn = null);
}

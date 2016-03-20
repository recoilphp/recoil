<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Throwable;

interface Kernel
{
    /**
     * Start a new strand of execution.
     *
     * The implementation must delay execution of the strand until the next
     * 'tick' of the kernel to allow the user to inspect the strand object
     * before execution begins.
     *
     * @param mixed $coroutine The strand's entry-point.
     */
    public function execute($coroutine) : Strand;

    /**
     * Run the kernel and wait for all strands to exit.
     *
     * Calls to wait() and waitForStrand() can be nested, which can be used in
     * synchronous code to block until a particular operation is complete.
     * However, care must be taken not to introduce deadlocks.
     *
     * @see Kernel::waitForStrand()
     * @see Kernel::interrupt()
     *
     * @return null
     * @throws Throwable The exception passed to {@see Kernel::interrupt()}.
     */
    public function wait();

    /**
     * Run the kernel and wait for a specific strand to exit.
     *
     * Calls to wait() and waitForStrand() can be nested, which can be used in
     * synchronous code to block until a particular operation is complete.
     * However, care must be taken not to introduce deadlocks.
     *
     * @see Kernel::wait()
     * @see Kernel::interrupt()
     *
     * @param Strand $strand The strand to wait for.
     *
     * @return mixed               The strand result, on success.
     * @throws Throwable           The exception passed to {@see Kernel::interrupt()}.
     * @throws Throwable           The exception thrown by the strand, if failed.
     * @throws TerminatedException The strand has been terminated.
     */
    public function waitForStrand(Strand $strand);

    /**
     * Run the kernel and wait for a specific coroutine to exit.
     *
     * This is a convenience method equivalent to:
     *
     *      $strand = $kernel->execute($coroutine);
     *      $kernel->waitForStrand($strand);
     *
     * @see Kernel::execute()
     * @see Kernel::waitForStrand()
     *
     * @param mixed $coroutine The strand's entry-point.
     *
     * @return mixed               The return value of the coroutine.
     * @throws Throwable           The exception produced by the coroutine, if any.
     * @throws Throwable           The exception used to interrupt the kernel.
     * @throws TerminatedException The strand has been terminated.
     */
    public function waitFor($coroutine);

    /**
     * Interrupt the kernel.
     *
     * Execution of all strands is paused and the given exception is thrown by
     * the current call to {@see Kernel::wait()}. wait() can be called again to
     * resume execution of remaining strands.
     *
     * @see Kernel::wait()
     * @see Kernel::waitForStrand()
     * @return null
     */
    public function interrupt(Throwable $exception);

    /**
     * Stop the kernel.
     *
     * @return null
     */
    public function stop();
}

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Recoil\Kernel\Exception\KernelPanicException;

interface Kernel extends Listener
{
    /**
     * Run the kernel until all strands exit, the kernel is stopped or a kernel
     * panic occurs.
     *
     * A kernel panic occurs when an exception occurs that is not handled by the
     * kernel's exception handler.
     *
     * This method returns immediately if the kernel is already running.
     *
     * @see Kernel::setExceptionHandler()
     *
     * @return null
     * @throws KernelPanicException An unhandled exception has stopped the kernel.
     */
    public function run();

    /**
     * Stop the kernel.
     *
     * @return null
     */
    public function stop();

    /**
     * Schedule a coroutine for execution on a new strand.
     *
     * Execution begins when the kernel is run; or, if called from within a
     * strand, when that strand cooperates.
     *
     * @param mixed $coroutine The coroutine to execute.
     */
    public function execute($coroutine) : Strand;

    /**
     * Set a user-defined exception handler function.
     *
     * The exception handler is invoked when a strand exits with an exception or
     * an internal error occurs in the kernel.
     *
     * The handler function signature is:
     *
     *     function (KernelPanicException $e)
     *
     * If the exception was caused by a strand the exception will be the
     * sub-type StrandException. $e->getPrevious() returns the exception that
     * triggered the call to the exception handler.
     *
     * If the exception handler is unable to handle the exception it can simply
     * re-throw it (or any other exception). This causes the kernel to panic and
     * stop running. This is also the behaviour when no exception handler is set.
     *
     * @param callable|null $fn The exception handler (null = remove).
     *
     * @return null
     */
    public function setExceptionHandler(callable $fn = null);
}

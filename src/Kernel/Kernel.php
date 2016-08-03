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
     * A kernel panic occurs when a strand throws an exception that is not
     * handled by the kernel's exception handler.
     *
     * This method returns immediately if the kernel is already running.
     *
     * @see Kernel::setExceptionHandler()
     *
     * @return null
     * @throws KernelPanicException A strand has caused a kernel panic.
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
     * Execution begins when the kernel is started; or, if called within a
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
     * The exception handler must accept a single KernelPanicException argument.
     * If the exception was caused by a strand the exception will be the sub-type
     * StrandException. The previous exception is the exception that triggered
     * the call to the exception handler.
     *
     * If the exception handler is unable to handle the exception it can simply
     * re-throw it (or any other exception). This causes the kernel panic and
     * stop running. This is also the behaviour when no exception handler is set.
     *
     * @param callable|null $fn The exception handler (null = remove).
     *
     * @return null
     */
    public function setExceptionHandler(callable $fn = null);
}

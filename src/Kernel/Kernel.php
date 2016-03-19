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
     * @see Kernel::interrupt()
     *
     * @return null
     * @throws Throwable The exception passed to {@see Kernel::interrupt()}.
     */
    public function wait();

    /**
     * Interrupt the kernel.
     *
     * Execution of all strands is paused and the given exception is thrown by
     * the current call to {@see Kernel::wait()}. wait() can be called again to
     * resume execution of remaining strands.
     *
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

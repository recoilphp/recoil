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
     * Run the kernel and wait for all strands to complete.
     *
     * If {@see Kernel::interrupt()} is called, wait() throws the exception.
     *
     * Recoil uses interrupts to indicate failed strands or strand observers,
     * but interrupts also be used by application code.
     *
     * @return null
     * @throws Throwable The exception passed to {@see Kernel::interrupt()}.
     */
    public function wait();

    /**
     * Interrupt the kernel.
     *
     * Execution is paused and the given exception is thrown by the current
     * call to {@see Kernel::wait()}.
     *
     * @return null
     */
    public function interrupt(Throwable $exception);
}

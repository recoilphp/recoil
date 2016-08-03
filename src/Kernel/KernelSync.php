<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Exception\KernelPanicException;
use Recoil\Kernel\Exception\KernelStoppedException;
use Throwable;

/**
 * A kernel that supports re-entrant, blocking operations.
 *
 * Stopping the kernel causes all calls to {@see KernelSync::executeSync()}
 * or {@see KernelSync::adoptSync()} to throw a {@see KernelStoppedException}.
 *
 * The kernel cannot run again until it has stopped completely. That is,
 * the PHP call-stack has unwound to the outer-most call to {@see Kernel::run()},
 * {@see Kernel::executeSync()} or {@see Kernel::adoptSync()}.
 */
interface KernelSync extends Kernel
{
    /**
     * Execute a coroutine on a new strand and block until it exits.
     *
     * If the kernel is not running, it is run until the strand exits, the
     * kernel is stopped explicitly, or a different strand causes a kernel panic.
     *
     * The kernel's exception handler is bypassed for this strand. Instead, if
     * the strand produces an exception it is re-thrown by this method.
     *
     * This is a convenience method equivalent to:
     *
     *      $strand = $kernel->execute($coroutine);
     *      $kernel->adoptSync($strand);
     *
     * @param mixed $coroutine The coroutine to execute.
     *
     * @return mixed                  The return value of the coroutine.
     * @throws Throwable              The exception produced by the coroutine.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException The kernel was stopped before the strand exited.
     * @throws KernelPanicException   An unhandled exception has stopped the kernel.
     */
    public function executeSync($coroutine);

    /**
     * Block until a strand exits.
     *
     * If the kernel is not running, it is run until the strand exits, the
     * kernel is stopped explicitly, or a different strand causes a kernel panic.
     *
     * The kernel's exception handler is bypassed for this strand. Instead, if
     * the strand produces an exception it is re-thrown by this method.
     *
     * @param Strand $strand The strand to wait for.
     *
     * @return mixed                  The return value of the coroutine.
     * @throws Throwable              The exception produced by the coroutine.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException The kernel was stopped before the strand exited.
     * @throws KernelPanicException   An unhandled exception has stopped the kernel.
     */
    public function adoptSync(Strand $strand);
}

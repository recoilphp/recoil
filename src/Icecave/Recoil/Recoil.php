<?php
namespace Icecave\Recoil;

use Icecave\Recoil\Kernel\SystemCall;

/**
 * Public facade for co-routine system calls.
 *
 * This class contains no implementation; it is a proxy for the kernel API
 * implementation provided by whichever co-routine kernel is currently being
 * used for execution.
 *
 * The interface {@see Icecave\Recoil\Kernel\KernelApiInterface} defines the
 * operations that are available; some kernels may provide additional features.
 */
abstract class Recoil
{
    /**
     * Invoke a method on the current kernel's system-call factory implementation.
     *
     * @see Icecave\Recoil\Kernel\KernelApiInterface
     *
     * @coroutine
     *
     * @param string $name
     * @param array  $arguments
     */
    public static function __callStatic($name, array $arguments)
    {
        return new SystemCall($name, $arguments);
    }
}

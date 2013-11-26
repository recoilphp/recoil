<?php
namespace Icecave\Recoil;

use Icecave\Recoil\Kernel\Api\KernelApiCall;

/**
 * Public facade for Kernel API calls.
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
     * Invoke a kernel API function.
     *
     * @see Icecave\Recoil\Kernel\KernelApiInterface
     * @see Icecave\Recoil\Kernel\KernelInterface::api()
     *
     * @coroutine
     *
     * @param string $name      The name of the kernel API function to invoke.
     * @param array  $arguments The arguments to the kernel API function.
     */
    public static function __callStatic($name, array $arguments)
    {
        return new KernelApiCall($name, $arguments);
    }
}

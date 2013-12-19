<?php
namespace Icecave\Recoil;

use Icecave\Recoil\Kernel\Api\KernelApiCall;
use Icecave\Recoil\Kernel\Kernel;
use React\EventLoop\LoopInterface;

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
     * [CO-ROUTINE] Invoke a kernel API function.
     *
     * @see Icecave\Recoil\Kernel\KernelApiInterface
     * @see Icecave\Recoil\Kernel\KernelInterface::api()
     *
     * @param string $name      The name of the kernel API function to invoke.
     * @param array  $arguments The arguments to the kernel API function.
     */
    public static function __callStatic($name, array $arguments)
    {
        return new KernelApiCall($name, $arguments);
    }

    /**
     * Create and run a new co-routine kernel.
     *
     * This is convenience method used to start the co-routine engine.
     * It should generally not be invoked from inside other co-routines.
     *
     * @param callable           $entryPoint    The co-routine to invoke.
     * @param LoopInterface|null $loopInterface The ReactPHP event-loop, or null to use the default.
     */
    public static function run(callable $entryPoint, LoopInterface $eventLoop = null)
    {
        $kernel = new Kernel($eventLoop);
        $kernel->execute($entryPoint());
        $kernel->eventLoop()->run();
    }
}

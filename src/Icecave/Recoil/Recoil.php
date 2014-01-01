<?php
namespace Icecave\Recoil;

use Icecave\Recoil\Kernel\Api\KernelApiCall;
use Icecave\Recoil\Kernel\Kernel;
use React\EventLoop\LoopInterface;

/**
 * Public facade for Kernel API calls.
 *
 * This class contains no implementation; it is a proxy for the kernel API
 * implementation provided by whichever coroutine kernel is currently being
 * used for execution.
 *
 * The interface {@see Icecave\Recoil\Kernel\KernelApiInterface} defines the
 * operations that are available; some kernels may provide additional features.
 */
abstract class Recoil
{
    /**
     * [COROUTINE] Invoke a kernel API function.
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
     * Create and run a new coroutine kernel.
     *
     * This is convenience method used to start the coroutine engine.
     * It should generally not be invoked from inside other coroutines.
     *
     * @param callable           $entryPoint    The coroutine to invoke.
     * @param LoopInterface|null $loopInterface The React event-loop, or null to use the default.
     */
    public static function run(callable $entryPoint, LoopInterface $eventLoop = null)
    {
        $kernel = new Kernel($eventLoop);
        $kernel->execute($entryPoint());
        $kernel->eventLoop()->run();
    }
}

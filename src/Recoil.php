<?php
namespace Recoil;

use Recoil\Kernel\Api\KernelApiCall;
use Recoil\Kernel\Kernel;
use React\EventLoop\LoopInterface;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * Public facade for Kernel API calls.
 *
 * This class contains no implementation; it is a proxy for the kernel API
 * implementation provided by whichever coroutine kernel is currently being
 * used for execution.
 *
 * The interface {@link Recoil\Kernel\KernelApiInterface} defines the
 * operations that are available; some kernels may provide additional features.
 *
 * @method strand() [COROUTINE] Get the strand the coroutine is executing on.
 * @method kernel() [COROUTINE] Get the coroutine kernel that the current strand is executing on.
 * @method eventLoop() [COROUTINE] Get the React event-loop that the coroutine kernel is executing on.
 * @method return_($value) [COROUTINE] Return a value to the calling coroutine.
 * @method throw_(Exception $exception) [COROUTINE] Throw an exception to the calling coroutine.
 * @method finally_(callable $callback) [COROUTINE] Register a callback to be invoked when the current coroutine ends.
 * @method terminate() [COROUTINE] Terminate execution of this strand.
 * @method sleep(float $timeout) [COROUTINE] Suspend execution for a specified period of time.
 * @method suspend(callable $callback) [COROUTINE] Suspend execution of the strand until it is resumed manually.
 * @method timeout(float $timeout, $coroutine) [COROUTINE] Execute a coroutine with a time limit.
 * @method all(array $coroutines) [COROUTINE] Execute the given coroutines concurrently.
 * @method noop() [COROUTINE] Resume the strand immediately.
 * @method cooperate() [COROUTINE] Suspend the strand until the next tick.
 * @method execute($coroutine) [COROUTINE] Execute a coroutine on its own strand.
 * @method stop(bool $stopEventLoop = true) [COROUTINE] Stop the coroutine kernel / event-loop.
 */
abstract class Recoil
{
    /**
     * [COROUTINE] Invoke a kernel API function.
     *
     * @see Recoil\Kernel\KernelApiInterface
     * @see Recoil\Kernel\KernelInterface::api()
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
     * @param callable           $entryPoint The coroutine to invoke.
     * @param LoopInterface|null $eventLoop  The React event-loop, or null to use the default.
     */
    public static function run(callable $entryPoint, LoopInterface $eventLoop = null)
    {
        $kernel = new Kernel($eventLoop);
        $kernel->execute($entryPoint());
        $kernel->eventLoop()->run();
    }
}

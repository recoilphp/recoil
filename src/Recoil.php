<?php

namespace Recoil;

use React\EventLoop\LoopInterface;
use Recoil\Kernel\Api\KernelApiCall;
use Recoil\Kernel\StandardKernel;
use Recoil\Kernel\Strand\Strand;

/**
 * Public facade for Kernel API calls.
 *
 * This class contains no implementation; it is a proxy for the kernel API
 * implementation provided by whichever coroutine kernel is currently being
 * used for execution.
 *
 * The interface {@link Recoil\Kernel\KernelApi} defines the operations that are
 * available; some kernels may provide additional features.
 *
 * @method static strand() [COROUTINE] Get the strand the coroutine is executing on.
 * @method static kernel() [COROUTINE] Get the coroutine kernel that the current strand is executing on.
 * @method static eventLoop() [COROUTINE] Get the React event-loop that the coroutine kernel is executing on.
 * @method static return_($value) [COROUTINE] Return a value to the calling coroutine.
 * @method static throw_(Exception $exception) [COROUTINE] Throw an exception to the calling coroutine.
 * @method static finally_(callable $callback) [COROUTINE] Register a callback to be invoked when the current coroutine ends.
 * @method static terminate() [COROUTINE] Terminate execution of this strand.
 * @method static sleep(float $timeout) [COROUTINE] Suspend execution for a specified period of time.
 * @method static suspend(callable $callback) [COROUTINE] Suspend execution of the strand until it is resumed manually.
 * @method static timeout(float $timeout, $coroutine) [COROUTINE] Execute a coroutine with a time limit.
 * @method static all(array $coroutines) [COROUTINE] Execute the given coroutines concurrently.
 * @method static noop() [COROUTINE] Resume the strand immediately.
 * @method static cooperate() [COROUTINE] Suspend the strand until the next tick.
 * @method static execute($coroutine) [COROUTINE] Execute a coroutine on its own strand.
 * @method static select(Strand $strand, array $strands) [COROUTINE] Wait for one or more of the given strands to exit.
 * @method static stop(bool $stopEventLoop = true) [COROUTINE] Stop the coroutine kernel / event-loop.
 */
abstract class Recoil
{
    /**
     * [COROUTINE] Invoke a kernel API function.
     *
     * @see Recoil\Kernel\KernelApi
     * @see Recoil\Kernel\Kernel::api()
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
        $kernel = new StandardKernel($eventLoop);
        $kernel->execute($entryPoint());
        $kernel->eventLoop()->run();
    }
}

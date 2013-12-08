<?php
namespace Icecave\Recoil\Kernel\Strand;

use Evenement\EventEmitterInterface;
use Exception;
use Icecave\Recoil\Coroutine\CoroutineInterface;

/**
 * A strand represents a user-space "thread" of execution.
 *
 * @event exit      The strand exited (for any reason).
 * @event success   The strand exited normally.
 * @event error     The strand exited due to an exception.
 * @event terminate The strand exited due to being terminated.
 * @event suspend   Execution of the strand has been suspended.
 * @event resumed   Execution of the strand has been resumed.
 */
interface StrandInterface extends EventEmitterInterface
{
    /**
     * Fetch the kernel on which this strand is executing.
     *
     * @return KernelInterface The co-routine kernel.
     */
    public function kernel();

    /**
     * Fetch the co-routine this strand is currently executing.
     *
     * @return CoroutineInterface The co-routine currently being executed.
     */
    public function current();

    /**
     * Push a co-routine onto the stack.
     *
     * The value must be adaptable using the kernel's co-routine adaptor.
     *
     * @param mixed $coroutine The co-routine to call.
     *
     * @return CoroutineInterface The adapted co-routine.
     */
    public function push($coroutine);

    /**
     * Pop the current co-routine off the stack.
     *
     * @return CoroutineInterface
     */
    public function pop();

    /**
     * Call the given co-routine.
     *
     * The value must be adaptable using the kernel's co-routine adaptor.
     *
     * @param mixed $coroutine The co-routine to call.
     *
     * @return CoroutineInterface|null The adapted co-routine, or null if no adaptation could be made.
     */
    public function call($coroutine);

    /**
     * Return a value to the calling co-routine.
     *
     * @param mixed $value The value to return.
     */
    public function returnValue($value = null);

    /**
     * Throw an exception to the calling co-routine.
     *
     * @param Exception $exception The exception to throw.
     */
    public function throwException(Exception $exception);

    /**
     * Suspend execution of this strand.
     *
     * The kernel will not call tick() until the strand is resumed.
     */
    public function suspend();

    /**
     * Resume execution of this strand.
     */
    public function resume();

    /**
     * Resume execution of this strand and send a value to the current co-routine.
     */
    public function resumeWithValue($value);

    /**
     * Resume execution of this strand and throw an excption to the current co-routine.
     */
    public function resumeWithException(Exception $exception);

    /**
     * Terminate execution of this strand.
     */
    public function terminate();

    /**
     * Check if the strand has exited.
     *
     * @return boolean True if the strand has exited; otherwise false.
     */
    public function hasExited();

    /**
     * Perform the next unit-of-work for this strand.
     */
    public function tick();
}

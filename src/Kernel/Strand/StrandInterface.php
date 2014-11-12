<?php
namespace Recoil\Kernel\Strand;

use Evenement\EventEmitterInterface;
use Exception;
use Recoil\Coroutine\CoroutineInterface;

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
     * @return KernelInterface The coroutine kernel.
     */
    public function kernel();

    /**
     * Fetch the coroutine this strand is currently executing.
     *
     * @return CoroutineInterface The coroutine currently being executed.
     */
    public function current();

    /**
     * Push a coroutine onto the stack.
     *
     * The value must be adaptable using the kernel's coroutine adaptor.
     *
     * @param mixed $coroutine The coroutine to call.
     *
     * @return CoroutineInterface The adapted coroutine.
     */
    public function push($coroutine);

    /**
     * Pop the current coroutine off the stack.
     *
     * @return CoroutineInterface
     */
    public function pop();

    /**
     * Call the given coroutine.
     *
     * The value must be adaptable using the kernel's coroutine adaptor.
     *
     * @param mixed $coroutine The coroutine to call.
     *
     * @return CoroutineInterface|null The adapted coroutine, or null if no adaptation could be made.
     */
    public function call($coroutine);

    /**
     * Return a value to the calling coroutine.
     *
     * @param mixed $value The value to return.
     */
    public function returnValue($value = null);

    /**
     * Throw an exception to the calling coroutine.
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
     * Resume execution of this strand and send a value to the current coroutine.
     *
     * @param mixed $value The value to send to the coroutine.
     */
    public function resumeWithValue($value);

    /**
     * Resume execution of this strand and throw an exception to the current coroutine.
     *
     * @param Exception $exception The exception to send to the coroutine.
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

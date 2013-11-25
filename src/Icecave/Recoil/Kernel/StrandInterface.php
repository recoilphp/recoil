<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Coroutine\CoroutineInterface;

/**
 * A strand represents a user-space "thread" of execution.
 */
interface StrandInterface
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
     * Terminate execution of this strand.
     */
    public function terminate();

    /**
     * Suspend execution of this strand.
     *
     * The kernel will not call tick() until the strand is resumed.
     */
    public function suspend();

    /**
     * Resume execution of this strand.
     */
    public function resume($value = null);

    /**
     * Resume execution of this strand and indicate an error condition.
     */
    public function resumeWithException(Exception $exception);

    /**
     * Instructs the strand to execute the next-tick immediately after the
     * current tick.
     */
    public function nextTickImmediate();

    /**
     * Instructs the strand not to execute the next-tick until the kernel
     * calls tick().
     */
    public function nextTickDeferred();

    /**
     * Perform the next unit-of-work for this strand.
     */
    public function tick();
}

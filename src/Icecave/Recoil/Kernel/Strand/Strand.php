<?php
namespace Icecave\Recoil\Kernel\Strand;

use Exception;
use Icecave\Recoil\Coroutine\CoroutineInterface;
use Icecave\Recoil\Kernel\KernelInterface;
use SplStack;

/**
 * A strand represents a user-space "thread" of execution.
 */
class Strand implements StrandInterface
{
    /**
     * @param KernelInterface The co-routine kernel.
     */
    public function __construct(KernelInterface $kernel, ResultHandlerInterface $resultHandler)
    {
        $this->kernel    = $kernel;
        $this->suspended = false;
        $this->stack     = new SplStack;

        $this->stack->push(
            new Detail\StackBase
        );

        $this->setResultHandler($resultHandler);
    }

    /**
     * Fetch the kernel on which this strand is executing.
     *
     * @return KernelInterface The co-routine kernel.
     */
    public function kernel()
    {
        return $this->kernel;
    }

    /**
     * Fetch the result handler that is notified when the strand produces a result.
     *
     * @return ResultHandlerInterface The strand's result handler.
     */
    public function resultHandler()
    {
        return $this->resultHandler;
    }

    /**
     * Set the result handler that is notified when the strand produces a result.
     *
     * @param ResultHandlerInterface $resultHandler The strand's result handler.
     */
    public function setResultHandler(ResultHandlerInterface $resultHandler)
    {
        $this->resultHandler = $resultHandler;
    }

    /**
     * Fetch the co-routine currently being executed.
     *
     * @return CoroutineInterface The co-routine currently being executed.
     */
    public function current()
    {
        return $this->stack->top();
    }

    /**
     * Push a co-routine onto the stack.
     *
     * The value must be adaptable using the kernel's co-routine adaptor.
     *
     * @param mixed $coroutine The co-routine to call.
     *
     * @return CoroutineInterface The adapted co-routine.
     */
    public function push($coroutine)
    {
        $coroutine = $this
            ->kernel()
            ->coroutineAdaptor()
            ->adapt($this, $coroutine);

        $this->stack->push($coroutine);

        return $coroutine;
    }

    /**
     * Pop the current co-routine off the stack.
     *
     * @return CoroutineInterface
     */
    public function pop()
    {
        return $this->stack->pop();
    }

    /**
     * Call the given co-routine immediately.
     *
     * The value must be adaptable using the kernel's co-routine adaptor.
     *
     * @param mixed $coroutine The co-routine to call.
     *
     * @return CoroutineInterface|null The adapted co-routine, or null if no adaptation could be made.
     */
    public function call($coroutine)
    {
        try {
            return $this->push($coroutine);
        } catch (Exception $e) {
            $this->current()->throwOnNextTick($e);
        }

        return null;
    }

    /**
     * Return a value to calling co-routine.
     *
     * @param mixed $value The value to return.
     */
    public function returnValue($value = null)
    {
        $this->pop();
        $this->current()->sendOnNextTick($value);
    }

    /**
     * Throw an exception to the calling co-routine.
     *
     * @param Exception $exception The exception to throw.
     */
    public function throwException(Exception $exception)
    {
        $this->pop();
        $this->current()->throwOnNextTick($exception);
    }

    /**
     * Suspend execution of this strand.
     */
    public function suspend()
    {
        $this->suspended = true;

        $this->kernel()->detachStrand($this);
    }

    /**
     * Resume execution of this strand.
     */
    public function resume()
    {
        $this->suspended = false;

        $this->kernel()->attachStrand($this);
    }

    /**
     * Resume execution of this strand and send a value to the current co-routine.
     */
    public function resumeWithValue($value)
    {
        $this->resume();
        $this->current()->sendOnNextTick($value);
    }

    /**
     * Resume execution of this strand and throw an excption to the current co-routine.
     */
    public function resumeWithException(Exception $exception)
    {
        $this->resume();
        $this->current()->throwOnNextTick($exception);
    }

    /**
     * Terminate this execution context.
     */
    public function terminate()
    {
        $this->resume();
        $this->current()->terminateOnNextTick();
    }

    /**
     * Perform the next unit-of-work for this strand.
     */
    public function tick()
    {
        while (!$this->suspended) {
            $this->current()->tick($this);
        }
    }

    private $kernel;
    private $resultHandler;
    private $suspended;
    private $stack;
}

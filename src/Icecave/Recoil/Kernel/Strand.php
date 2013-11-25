<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Coroutine\CoroutineInterface;
use SplStack;
use React\Promise\Deferred;

/**
 * A strand represents a user-space "thread" of execution.
 */
class Strand implements StrandInterface
{
    /**
     * @param KernelInterface The co-routine kernel.
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel    = $kernel;
        $this->suspended = false;
        $this->deferred  = new Deferred;
        $this->stack     = new SplStack;

        $this->stack->push(
            new StackBase($this->deferred->resolver())
        );
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
     */
    public function push($coroutine)
    {
        $coroutine = $this
            ->kernel()
            ->coroutineAdaptor()
            ->adapt($this, $coroutine);

        $this->stack->push($coroutine);
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
     */
    public function call($coroutine)
    {
        try {
            $this->push($coroutine);
        } catch (Exception $e) {
            $this->nextValue = null;
            $this->nextException = $e;
        }
    }

    /**
     * Return a value to calling co-routine.
     *
     * @param mixed $value The value to return.
     */
    public function returnValue($value = null)
    {
        $this->nextValue = $value;
        $this->nextException = null;

        $this->pop();
    }

    /**
     * Throw an exception to the calling co-routine.
     *
     * @param Exception $exception The exception to throw.
     */
    public function throwException(Exception $exception)
    {
        $this->nextValue = null;
        $this->nextException = $exception;

        $this->pop();
    }

    /**
     * Terminate this execution context.
     */
    public function terminate()
    {
        $this->stack = new SplStack;

        $this->suspend();
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
    public function resume($value = null)
    {
        $this->suspended = false;

        $this->nextValue = $value;
        $this->nextException = null;

        $this->kernel()->attachStrand($this);
    }

    /**
     * Resume execution of this strand and indicate an error condition.
     */
    public function resumeWithException(Exception $exception)
    {
        $this->suspended = false;

        $this->nextValue = null;
        $this->nextException = $exception;

        $this->kernel()->attachStrand($this);
    }

    /**
     * Perform the next unit-of-work for this strand.
     */
    public function tick()
    {
        while (!$this->suspended) {
            $value = $this->nextValue;
            $exception = $this->nextException;
            $this->nextValue = null;
            $this->nextException = null;

            $this->current()->tick($this, $value, $exception);
        }
    }

    /**
     * Register promise handlers.
     *
     * @param callable|null $fulfilledHandler
     * @param callable|null $errorHandler
     * @param callable|null $progressHandler
     *
     * @return PromiseInterface
     */
    public function then($fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        if ($errorHandler && !$this->stack->isEmpty()) {
            $this->stack->bottom()->disableExceptionPropagation();
        }

        return $this->deferred->then($fulfilledHandler, $errorHandler, $progressHandler);
    }

    private $kernel;
    private $suspended;
    private $deferred;
    private $stack;
    private $nextValue;
    private $nextException;
}

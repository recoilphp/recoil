<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Coroutine\CoroutineInterface;
use SplStack;

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
        $this->kernel = $kernel;
        $this->stack = new SplStack;
        $this->stack->push(new StackRoot);

        $this->nextTickDeferred();
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
            $this->nextTickImmediate();
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
        $this->nextTickDeferred();
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
        $this->nextTickDeferred();
    }

    /**
     * Terminate this execution context.
     */
    public function terminate()
    {
        $this->stack = new SplStack;
    }

    /**
     * Suspend execution of this strand.
     */
    public function suspend()
    {
        $this->kernel()->detachStrand($this);
        $this->nextTickDeferred();
    }

    /**
     * Resume execution of this strand.
     */
    public function resume($value = null)
    {
        $this->nextValue = $value;
        $this->nextException = null;

        $this->kernel()->attachStrand($this);
        $this->nextTickDeferred();
    }

    /**
     * Resume execution of this strand.
     */
    public function resumeWithException(Exception $exception)
    {
        $this->nextValue = null;
        $this->nextException = $exception;

        $this->kernel()->attachStrand($this);
        $this->nextTickDeferred();
    }

    /**
     * Instructs the strand to resume immediately after the next tick.
     */
    public function nextTickImmediate()
    {
        $this->immediate = true;
    }

    /**
     * Instructs the strand to defer after the next tick.
     */
    public function nextTickDeferred()
    {
        $this->immediate = false;
    }

    /**
     * Perform the next unit-of-work for this strand.
     */
    public function tick()
    {
        do {
            if ($this->stack->isEmpty()) {
                $this->suspend();

                return;
            }

            $value = $this->nextValue;
            $exception = $this->nextException;
            $this->nextValue = $this->nextException = null;

            $this->current()->tick($this, $value, $exception);

        } while ($this->immediate);
    }

    private $kernel;
    private $stack;
    private $immediate;
    private $nextValue;
    private $nextException;
}

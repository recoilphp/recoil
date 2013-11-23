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
            $coroutine = $this
                ->kernel()
                ->coroutineAdaptor()
                ->adapt($this, $coroutine);

            $this->stack->push($coroutine);
        } catch (Exception $e) {
            $this->current()->setException($e);
        }
    }

    /**
     * Return a value to calling co-routine.
     *
     * @param mixed $value The value to return.
     */
    public function returnValue($value = null)
    {
        $this->pop();

        $this->current()->setValue($value);
    }

    /**
     * Throw an exception to the calling co-routine.
     *
     * @param Exception $exception The exception to throw.
     */
    public function throwException(Exception $exception)
    {
        $this->pop();

        $this->current()->setException($exception);
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
        $this->suspended = true;
    }

    /**
     * Resume execution of this strand.
     */
    public function resume($value = null)
    {
        $this->suspended = false;

        $this->current()->setValue($value);
        $this->kernel()->attachStrand($this);
    }

    /**
     * Resume execution of this strand.
     */
    public function resumeWithException(Exception $exception = null)
    {
        $this->suspended = false;

        $this->current()->setException($exception);
        $this->kernel()->attachStrand($this);
    }

    public function tick()
    {
        if ($this->suspended || $this->stack->isEmpty()) {
            return false;
        }

        $this->current()->tick($this);

        return true;
    }

    private $kernel;
    private $stack;
    private $suspended;
}

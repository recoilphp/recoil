<?php
namespace Recoil\Kernel\Strand;

use Evenement\EventEmitter;
use Exception;
use Recoil\Coroutine\CoroutineInterface;
use Recoil\Kernel\KernelInterface;
use SplStack;

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
class Strand extends EventEmitter implements StrandInterface
{
    /**
     * @param KernelInterface The coroutine kernel.
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel    = $kernel;
        $this->suspended = false;
        $this->stack     = new SplStack;

        $this->stack->push(new StackBase);

        $kernel->attachStrand($this);
    }

    /**
     * Fetch the kernel on which this strand is executing.
     *
     * @return KernelInterface The coroutine kernel.
     */
    public function kernel()
    {
        return $this->kernel;
    }

    /**
     * Fetch the coroutine currently being executed.
     *
     * @return CoroutineInterface The coroutine currently being executed.
     */
    public function current()
    {
        return $this->stack->top();
    }

    /**
     * Push a coroutine onto the stack.
     *
     * The value must be adaptable using the kernel's coroutine adaptor.
     *
     * @param mixed $coroutine The coroutine to call.
     *
     * @return CoroutineInterface The adapted coroutine.
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
     * Pop the current coroutine off the stack.
     *
     * @return CoroutineInterface
     */
    public function pop()
    {
        return $this->stack->pop();
    }

    /**
     * Call the given coroutine immediately.
     *
     * The value must be adaptable using the kernel's coroutine adaptor.
     *
     * @param mixed $coroutine The coroutine to call.
     *
     * @return CoroutineInterface|null The adapted coroutine, or null if no adaptation could be made.
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
     * Return a value to calling coroutine.
     *
     * @param mixed $value The value to return.
     */
    public function returnValue($value = null)
    {
        $this->pop();
        $this->current()->sendOnNextTick($value);
    }

    /**
     * Throw an exception to the calling coroutine.
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
        if ($this->suspended) {
            return;
        }

        $this->suspended = true;

        $this->kernel()->detachStrand($this);

        $this->emit('suspend', [$this]);
    }

    /**
     * Resume execution of this strand.
     */
    public function resume()
    {
        if (!$this->suspended) {
            return;
        }

        $this->suspended = false;

        $this->kernel()->attachStrand($this);

        $this->emit('resume', [$this]);
    }

    /**
     * Resume execution of this strand and send a value to the current coroutine.
     */
    public function resumeWithValue($value)
    {
        $this->current()->sendOnNextTick($value);
        $this->resume();
    }

    /**
     * Resume execution of this strand and throw an excption to the current coroutine.
     */
    public function resumeWithException(Exception $exception)
    {
        $this->current()->throwOnNextTick($exception);
        $this->resume();
    }

    /**
     * Terminate this execution context.
     */
    public function terminate()
    {
        $this->current()->terminateOnNextTick();
        $this->resume();
    }

    /**
     * Check if the strand has exited.
     *
     * @return boolean True if the strand has exited; otherwise false.
     */
    public function hasExited()
    {
        return $this->stack->isEmpty();
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
    private $suspended;
    private $stack;
}

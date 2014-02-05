<?php
namespace Recoil\Kernel\Strand;

use Evenement\EventEmitter;
use Exception;
use LogicException;
use Recoil\Coroutine\CoroutineInterface;
use Recoil\Kernel\KernelInterface;

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
        $this->stack     = [];

        $this->stack[] = new StackBase;

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
        return end($this->stack);
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

        $coroutine->initialize($this);
        $coroutine->emit('initialize', [$this, $coroutine]);

        $this->stack[] = $coroutine;

        return $coroutine;
    }

    /**
     * Pop the current coroutine off the stack.
     *
     * @return CoroutineInterface
     */
    public function pop()
    {
        $coroutine = array_pop($this->stack);

        $coroutine->finalize($this);
        $coroutine->emit('finalize', [$this, $coroutine]);
        $coroutine->removeAllListeners();

        return $coroutine;
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
            $coroutine = $this->push($coroutine);
        } catch (Exception $e) {
            $this->resumeWithException($e);

            return null;
        }

        $this->tickLogic = function () {
            $this->tickLogic = null;
            $this->current()->call($this);
        };

        return $coroutine;
    }

    /**
     * Return a value to calling coroutine.
     *
     * @param mixed $value The value to return.
     */
    public function returnValue($value = null)
    {
        $this->pop();
        $this->resumeWithValue($value);
    }

    /**
     * Throw an exception to the calling coroutine.
     *
     * @param Exception $exception The exception to throw.
     */
    public function throwException(Exception $exception)
    {
        $this->pop();
        $this->resumeWithException($exception);
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

        $this->kernel->detachStrand($this);

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

        $this->kernel->attachStrand($this);

        $this->emit('resume', [$this]);
    }

    /**
     * Resume execution of this strand and send a value to the current coroutine.
     *
     * @param mixed $value The value to send to the coroutine.
     */
    public function resumeWithValue($value)
    {
        $this->resume();

        $this->tickLogic = function () use ($value) {
            $this->tickLogic = null;
            $this->current()->resumeWithValue($this, $value);
        };
    }

    /**
     * Resume execution of this strand and throw an exception to the current coroutine.
     *
     * @param Exception $exception The exception to send to the coroutine.
     */
    public function resumeWithException(Exception $exception)
    {
        $this->resume();

        $this->tickLogic = function () use ($exception) {
            $this->tickLogic = null;
            $this->current()->resumeWithException($this, $exception);
        };
    }

    /**
     * Terminate this execution context.
     */
    public function terminate()
    {
        $this->resume();

        $tickLogic = null;
        $tickLogic = function () use (&$tickLogic) {
            $this->current()->terminate($this);

            // Check if the tick logic has been changed, if not continue with
            // termination of the strand.
            if ($this->tickLogic === $tickLogic) {
                $this->pop();
                // Note that tickLogic is not reset to null.
            }
        };

        $this->tickLogic = $tickLogic;
    }

    /**
     * Check if the strand has exited.
     *
     * @return boolean True if the strand has exited; otherwise false.
     */
    public function hasExited()
    {
        return !$this->stack;
    }

    /**
     * Perform the next unit-of-work for this strand.
     */
    public function tick()
    {
        while (!$this->suspended) {
            $tickLogic = $this->tickLogic;

            if (!$tickLogic) {
                throw new LogicException('No action has been requested.');
            }

            $tickLogic();
        }
    }

    private $kernel;
    private $suspended;
    private $stack;
    private $tickLogic;
}

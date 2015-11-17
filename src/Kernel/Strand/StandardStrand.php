<?php

namespace Recoil\Kernel\Strand;

use Evenement\EventEmitter;
use Exception;
use LogicException;
use Recoil\Coroutine\Coroutine;
use Recoil\Kernel\Kernel;

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
class StandardStrand extends EventEmitter implements Strand
{
    /**
     * @param Kernel The coroutine kernel.
     */
    public function __construct(Kernel $kernel)
    {
        $this->kernel    = $kernel;
        $this->suspended = false;
        $this->stack     = [];

        $this->stack[] = $this->current = new StackBase();

        $kernel->attachStrand($this);
    }

    /**
     * Fetch the kernel on which this strand is executing.
     *
     * @return Kernel The coroutine kernel.
     */
    public function kernel()
    {
        return $this->kernel;
    }

    /**
     * Fetch the coroutine currently being executed.
     *
     * @return Coroutine The coroutine currently being executed.
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * Push a coroutine onto the stack.
     *
     * The value must be adaptable using the kernel's coroutine adaptor.
     *
     * @param mixed $coroutine The coroutine to call.
     *
     * @return Coroutine The adapted coroutine.
     */
    public function push($coroutine)
    {
        $coroutine = $this
            ->kernel()
            ->coroutineAdaptor()
            ->adapt($this, $coroutine);

        $this->stack[] = $coroutine;
        $this->current = $coroutine;

        return $coroutine;
    }

    /**
     * Pop the current coroutine off the stack.
     *
     * @return Coroutine
     */
    public function pop()
    {
        $coroutine     = array_pop($this->stack);
        $this->current = end($this->stack);

        $coroutine->finalize($this);

        return $coroutine;
    }

    /**
     * Call the given coroutine immediately.
     *
     * The value must be adaptable using the kernel's coroutine adaptor.
     *
     * @param mixed $coroutine The coroutine to call.
     *
     * @return Coroutine|null The adapted coroutine, or null if no adaptation could be made.
     */
    public function call($coroutine)
    {
        try {
            $coroutine = $this->push($coroutine);
        } catch (Exception $e) {
            $this->resumeWithException($e);

            return null;
        }

        $this->state      = self::STATE_CALL;
        $this->resumeData = null;

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
    }

    /**
     * Resume execution of this strand and send a value to the current coroutine.
     *
     * @param mixed $value The value to send to the coroutine.
     */
    public function resumeWithValue($value)
    {
        $this->resume();

        $this->state      = self::STATE_RESUME;
        $this->resumeData = $value;
    }

    /**
     * Resume execution of this strand and throw an exception to the current coroutine.
     *
     * @param Exception $exception The exception to send to the coroutine.
     */
    public function resumeWithException(Exception $exception)
    {
        $this->resume();

        $this->state      = self::STATE_EXCEPTION;
        $this->resumeData = $exception;
    }

    /**
     * Terminate this execution context.
     */
    public function terminate()
    {
        $this->resume();

        $this->state      = self::STATE_TERMINATE;
        $this->resumeData = null;
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
            if ($this->state === self::STATE_CALL) {
                $this->state = null;
                $this->current->call($this);
            } elseif ($this->state === self::STATE_RESUME) {
                $this->state = null;
                $this->current->resumeWithValue($this, $this->resumeData);
            } elseif ($this->state === self::STATE_EXCEPTION) {
                $this->state = null;
                $this->current->resumeWithException($this, $this->resumeData);
            } elseif (self::STATE_TERMINATE === $this->state) {
                $this->current->terminate($this);

                // Check if the state has been changed, if not continue with
                // termination of the strand.
                if ($this->state === self::STATE_TERMINATE) {
                    $this->pop();
                }
            } else {
                throw new LogicException('No action has been requested.');
            }
        }
    }

    /**
     * Resume execution of this strand.
     */
    private function resume()
    {
        if (!$this->suspended) {
            return;
        }

        $this->suspended = false;

        $this->kernel->attachStrand($this);
    }

    const STATE_CALL      = 1;
    const STATE_RESUME    = 2;
    const STATE_EXCEPTION = 3;
    const STATE_TERMINATE = 4;

    private $kernel;
    private $suspended;
    private $stack;
    private $current;
    private $state;
    private $resumeData;
}

<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Icecave\Recoil\Kernel\StrandInterface;
use React\Promise\PromiseInterface;
use RuntimeException;

/**
 * A co-routine wrapper for ReactPHP promises.
 */
class PromiseCoroutine implements CoroutineInterface
{
    /**
     * @param PromiseInterface $promise The ReactPHP promise.
     */
    public function __construct(PromiseInterface $promise)
    {
        $this->promise = $promise;
    }

    /**
     * Perform the next unit-of-work.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function tick(StrandInterface $strand)
    {
        if (null === $this->strand) {
            $this->strand = $strand;
            $this->promise->then(
                [$this, 'onPromiseFulfilled'],
                [$this, 'onPromiseRejected']
            );
            $strand->suspend();
        } elseif ($this->exception) {
            $strand->throwException($this->exception);
        } else {
            $strand->returnValue($this->value);
        }
    }

    /**
     * Set the value to send to the co-routine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function setValue($value)
    {
        $this->value = $value;
        $this->exception = null;
    }

    /**
     * Set the exception to throw to the co-routine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function setException(Exception $exception)
    {
        $this->value = null;
        $this->exception = $exception;
    }

    /**
     * Cancel execution of the co-routine.
     */
    public function cancel()
    {
        $this->promise = null;
        $this->strand = null;
        $this->value = null;
        $this->exception = null;
    }

    /**
     * @param mixed $value
     */
    public function onPromiseFulfilled($value)
    {
        if ($this->strand) {
            $this->strand->resume($value);
        }
    }

    /**
     * @param mixed $reason
     */
    public function onPromiseRejected($reason)
    {
        if ($this->strand) {
            if (!$reason instanceof Exception) {
                $reason = new RuntimeException($reason);
            }

            $this->strand->resumeWithException($reason);
        }
    }

    private $promise;
    private $strand;
    private $value;
    private $exception;
}

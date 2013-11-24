<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Icecave\Recoil\Kernel\StrandInterface;
use React\Promise\PromiseInterface;
use RuntimeException;

/**
 * A co-routine that resumes when a promise is fulfilled or rejected.
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
     * @param StrandInterface $strand    The currently executing strand.
     * @param mixed           $value
     * @param Exception|null  $exception
     */
    public function tick(StrandInterface $strand, $value = null, Exception $exception = null)
    {
        if (null === $this->strand) {
            $this->strand = $strand;
            $this->strand->suspend();
            $this->promise->then(
                [$this, 'onPromiseFulfilled'],
                [$this, 'onPromiseRejected']
            );
        } elseif ($exception) {
            $strand->throwException($exception);
        } else {
            $strand->returnValue($value);
        }
    }

    /**
     * Cancel execution of the co-routine.
     */
    public function cancel()
    {
        $this->promise = null;
        $this->strand = null;
    }

    /**
     * @param mixed $value
     */
    public function onPromiseFulfilled($value)
    {
        if (!$this->strand) {
            return;
        }

        $this->strand->resume($value);
    }

    /**
     * @param mixed $reason
     */
    public function onPromiseRejected($reason)
    {
        if (!$this->strand) {
            return;
        }

        if (!$reason instanceof Exception) {
            $reason = new RuntimeException($reason);
        }

        $this->strand->resumeWithException($reason);
    }

    private $promise;
    private $strand;
}

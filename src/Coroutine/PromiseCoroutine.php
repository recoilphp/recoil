<?php
namespace Recoil\Coroutine;

use Exception;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use Recoil\Coroutine\Exception\PromiseRejectedException;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * A coroutine that resumes when a promise is fulfilled or rejected.
 */
class PromiseCoroutine implements CoroutineInterface
{
    use CoroutineTrait;

    /**
     * @param PromiseInterface $promise The wrapped promise object.
     */
    public function __construct(PromiseInterface $promise)
    {
        $this->promise = $promise;
    }

    /**
     * Start the coroutine.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function call(StrandInterface $strand)
    {
        $strand->suspend();

        $this->promise->then(
            function ($value) use ($strand) {
                if ($this->promise) {
                    $strand->resumeWithValue($value);
                }
            },
            function ($reason) use ($strand) {
                if ($this->promise) {
                    $strand->resumeWithException(
                        $this->adaptReasonToException($reason)
                    );
                }
            }
        );
    }

    /**
     * Inform the coroutine that the executing strand is being terminated.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function terminate(StrandInterface $strand)
    {
        if ($this->promise instanceof CancellablePromiseInterface) {
            $this->promise->cancel();
        }
    }

    /**
     * Finalize the coroutine.
     *
     * This method is invoked after the coroutine is popped from the call stack.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function finalize(StrandInterface $strand)
    {
        $this->promise = null;
    }

    /**
     * Adapt a promise rejection reason into an exception.
     *
     * @param mixed $reason
     *
     * @return Exception
     */
    protected function adaptReasonToException($reason)
    {
        if ($reason instanceof Exception) {
            return $reason;
        }

        return new PromiseRejectedException($reason);
    }

    private $promise;
}

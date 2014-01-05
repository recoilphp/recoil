<?php
namespace Recoil\Coroutine;

use Exception;
use Recoil\Coroutine\Exception\PromiseRejectedException;
use Recoil\Kernel\Strand\StrandInterface;
use React\Promise\PromiseInterface;

/**
 * A coroutine that resumes when a promise is fulfilled or rejected.
 */
class PromiseCoroutine extends AbstractCoroutine
{
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

    private $promise;
}

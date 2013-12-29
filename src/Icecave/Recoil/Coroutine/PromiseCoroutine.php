<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Icecave\Recoil\Kernel\Strand\StrandInterface;
use React\Promise\PromiseInterface;
use RuntimeException;

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

        parent::__construct();
    }

    /**
     * Fetch the wrapped promise object.
     *
     * @return PromiseInterface The wrapped promise object.
     */
    public function promise()
    {
        return $this->promise;
    }

    /**
     * Invoked when tick() is called for the first time.
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
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     * @param mixed           $value  The value passed to sendOnNextTick().
     */
    public function resumeWithValue(StrandInterface $strand, $value)
    {
        $strand->returnValue($value);
    }

    /**
     * Invoked when tick() is called after throwOnNextTick().
     *
     * @param StrandInterface $strand    The strand that is executing the coroutine.
     * @param Exception       $exception The exception passed to throwOnNextTick().
     */
    public function resumeWithException(StrandInterface $strand, Exception $exception)
    {
        $strand->throwException($exception);
    }

    /**
     * Invoked when tick() is called after terminateOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function terminate(StrandInterface $strand)
    {
        $this->promise = null;

        $strand->pop();
        $strand->terminate();
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

        return new RuntimeException($reason);
    }

    private $promise;
}

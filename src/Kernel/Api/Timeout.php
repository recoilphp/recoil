<?php
namespace Recoil\Kernel\Api;

use Exception;
use Recoil\Coroutine\AbstractCoroutine;
use Recoil\Kernel\Exception\TimeoutException;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * Internal implementation of KernelApiInterface::timeout().
 *
 * @internal
 */
class Timeout extends AbstractCoroutine
{
    public function __construct($timeout, $coroutine)
    {
        $this->timeout = $timeout;
        $this->timeoutReached = false;
        $this->coroutine = $coroutine;

        parent::__construct();
    }

    /**
     * Invoked when tick() is called for the first time.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function call(StrandInterface $strand)
    {
        $this->coroutine = $strand->call($this->coroutine);

        $this->timer = $strand
            ->kernel()
            ->eventLoop()
            ->addTimer(
                $this->timeout,
                function () use ($strand) {
                    $strand->terminate();
                    $this->timeoutReached = true;
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
        $this->timer->cancel();

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
        $this->timer->cancel();

        $strand->throwException($exception);
    }

    /**
     * Invoked when tick() is called after terminateOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function terminate(StrandInterface $strand)
    {
        if ($this->timeoutReached) {
            $strand->resumeWithException(new TimeoutException);
        } else {
            $this->timer->cancel();

            $strand->pop();
            $strand->terminate();
        }
    }

    private $timeout;
    private $timeoutReached;
    private $coroutine;
    private $timer;
}

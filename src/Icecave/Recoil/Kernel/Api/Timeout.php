<?php
namespace Icecave\Recoil\Kernel\Api;

use Exception;
use Icecave\Recoil\Coroutine\AbstractCoroutine;
use Icecave\Recoil\Kernel\Exception\TimeoutException;
use Icecave\Recoil\Kernel\Strand\StrandInterface;

class Timeout extends AbstractCoroutine
{
    public function __construct($timeout, $coroutine)
    {
        $this->timeout = $timeout;
        $this->coroutine = $coroutine;

        parent::__construct();
    }

    /**
     * Invoked when tick() is called for the first time.
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
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
                    $this->coroutine->terminate($strand);
                    $strand->resumeWithException(new TimeoutException);
                }
            );
    }

    /**
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     * @param mixed           $value  The value passed to sendOnNextTick().
     */
    public function resume(StrandInterface $strand, $value)
    {
        $this->timer->cancel();

        $strand->returnValue($value);
    }

    /**
     * Invoked when tick() is called after throwOnNextTick().
     *
     * @param StrandInterface $strand    The strand that is executing the co-routine.
     * @param Exception       $exception The exception passed to throwOnNextTick().
     */
    public function error(StrandInterface $strand, Exception $exception)
    {
        $this->timer->cancel();

        $strand->throwException($exception);
    }

    /**
     * Invoked when tick() is called after terminateOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function terminate(StrandInterface $strand)
    {
        $this->timer->cancel();

        $strand->pop();
        $strand->terminate();
    }

    private $timeout;
    private $coroutine;
    private $timer;
}

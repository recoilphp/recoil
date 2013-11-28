<?php
namespace Icecave\Recoil\Kernel\Api;

use Exception;
use Icecave\Recoil\Coroutine\AbstractCoroutine;
use Icecave\Recoil\Kernel\Strand\StrandInterface;

class Sleep extends AbstractCoroutine
{
    public function __construct($timeout)
    {
        $this->timeout = $timeout;

        parent::__construct();
    }

    /**
     * Invoked when tick() is called for the first time.
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function call(StrandInterface $strand)
    {
        $strand->suspend();

        $this->timer = $strand
            ->kernel()
            ->eventLoop()
            ->addTimer(
                $this->timeout,
                function () use ($strand) {
                    $strand->resumeWithValue(null);
                }
            );
    }

    /**
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
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
     * @param StrandInterface $strand    The strand that is executing the co-routine.
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
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function terminate(StrandInterface $strand)
    {
        if ($this->timer) {
            $this->timer->cancel();
            $this->timer = null;
        }

        $strand->pop();
        $strand->terminate();
    }

    private $timeout;
    private $timer;
}

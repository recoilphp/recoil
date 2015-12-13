<?php

namespace Recoil\Kernel\Api;

use Exception;
use Recoil\Coroutine\Coroutine;
use Recoil\Coroutine\CoroutineTrait;
use Recoil\Kernel\Exception\TimeoutException;
use Recoil\Kernel\Strand\Strand;

/**
 * Internal implementation of KernelApi::timeout().
 *
 * @access private
 */
class Timeout implements Coroutine
{
    use CoroutineTrait;

    public function __construct($timeout, $coroutine)
    {
        $this->timeout   = $timeout;
        $this->coroutine = $coroutine;
    }

    /**
     * Invoked when tick() is called for the first time.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function call(Strand $strand)
    {
        $this->timer = $strand
            ->kernel()
            ->eventLoop()
            ->addTimer(
                $this->timeout,
                function () use ($strand) {
                    $this->timer = null;
                    $strand->terminate();
                }
            );

        $strand->call($this->coroutine);
    }

    /**
     * Inform the coroutine that the executing strand is being terminated.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function terminate(Strand $strand)
    {
        if (!$this->timer) {
            // Stop termination of the strand and instead propagate a timeout exception.
            $strand->throwException(new TimeoutException());
        }
    }
    /**
     * Finalize the coroutine.
     *
     * This method is invoked after the coroutine is popped from the call stack.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function finalize(Strand $strand)
    {
        if ($this->timer) {
            $this->timer->cancel();
            $this->timer = null;
        }
    }

    private $timeout;
    private $coroutine;
    private $timer;
}

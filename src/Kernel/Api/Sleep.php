<?php

namespace Recoil\Kernel\Api;

use Recoil\Coroutine\Coroutine;
use Recoil\Coroutine\CoroutineTrait;
use Recoil\Kernel\Strand\Strand;

/**
 * Internal implementation of KernelApi::sleep().
 *
 * @access private
 */
class Sleep implements Coroutine
{
    use CoroutineTrait;

    public function __construct($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * Invoked when tick() is called for the first time.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function call(Strand $strand)
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
    private $timer;
}

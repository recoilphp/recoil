<?php
namespace Recoil\Kernel\Api;

use Recoil\Coroutine\AbstractCoroutine;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * Internal implementation of KernelApiInterface::sleep().
 *
 * @internal
 */
class Sleep extends AbstractCoroutine
{
    public function __construct($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * Invoked when tick() is called for the first time.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
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
     * Finalize the coroutine.
     *
     * This method is invoked after the coroutine is popped from the call stack.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function finalize(StrandInterface $strand)
    {
        if ($this->timer) {
            $this->timer->cancel();
            $this->timer = null;
        }
    }

    private $timeout;
    private $timer;
}

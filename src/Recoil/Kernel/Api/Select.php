<?php
namespace Recoil\Kernel\Api;

use Recoil\Coroutine\CoroutineInterface;
use Recoil\Coroutine\CoroutineTrait;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * Internal implementation of KernelApiInterface::select().
 *
 * @internal
 */
class Select implements CoroutineInterface
{
    use CoroutineTrait;

    public function __construct(array $strands)
    {
        $this->waitStrands = $strands;
        $this->isResuming = false;
    }

    /**
     * Start the coroutine.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function call(StrandInterface $strand)
    {
        $this->suspendedStrand = $strand;
        $this->suspendedStrand->suspend();

        foreach ($this->waitStrands as $strand) {
            if ($strand->hasExited()) {
                $this->scheduleResume();
            } else {
                $strand->on(
                    'exit',
                    [$this, 'scheduleResume']
                );
            }
        }
    }

    /**
     * Resume execution of a suspended coroutine by passing it a value.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     * @param mixed           $value  The value to send to the coroutine.
     */
    public function resumeWithValue(StrandInterface $strand, $value)
    {
        $exitedStrands = array_filter(
            $this->waitStrands,
            function ($strand) {
                return $strand->hasExited();
            }
        );

        $strand->returnValue($exitedStrands);
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
        $this->removeListeners();
    }

    public function scheduleResume()
    {
        if ($this->isResuming) {
            return;
        }

        $this
            ->suspendedStrand
            ->kernel()
            ->eventLoop()
            ->nextTick(
                function () {
                    $this->suspendedStrand->resumeWithValue(null);
                }
            );

        $this->isResuming = true;
    }

    protected function removeListeners()
    {
        foreach ($this->waitStrands as $strand) {
            $strand->removeListener(
                'exit',
                [$this, 'scheduleResume']
            );
        }
    }

    private $waitStrands;
    private $suspendedStrand;
    private $isResuming;
    private $resumer;
}

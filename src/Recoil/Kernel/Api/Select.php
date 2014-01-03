<?php
namespace Recoil\Kernel\Api;

use Exception;
use Recoil\Coroutine\AbstractCoroutine;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * Internal implementation of KernelApiInterface::select().
 *
 * @internal
 */
class Select extends AbstractCoroutine
{
    public function __construct(array $strands)
    {
        $this->waitStrands = $strands;
        $this->isResuming = false;

        parent::__construct();
    }

    /**
     * Invoked when tick() is called for the first time.
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
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     * @param mixed           $value  The value passed to sendOnNextTick().
     */
    public function resumeWithValue(StrandInterface $strand, $value)
    {
        $this->removeListeners();

        $exitedStrands = array_filter(
            $this->waitStrands,
            function ($strand) {
                return $strand->hasExited();
            }
        );

        $strand->returnValue($exitedStrands);
    }

    /**
     * Invoked when tick() is called after throwOnNextTick().
     *
     * @codeCoverageIgnore
     *
     * @param StrandInterface $strand    The strand that is executing the coroutine.
     * @param Exception       $exception The exception passed to throwOnNextTick().
     */
    public function resumeWithException(StrandInterface $strand, Exception $exception)
    {
        throw new Exception('Not supported.');
    }

    /**
     * Invoked when tick() is called after terminateOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function terminate(StrandInterface $strand)
    {
        $this->removeListeners();

        $strand->pop();
        $strand->terminate();
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

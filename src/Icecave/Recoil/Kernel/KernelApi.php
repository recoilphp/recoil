<?php
namespace Icecave\Recoil\Kernel;

use Exception;

class KernelApi implements KernelApiInterface
{
    /**
     * Return a value to the calling co-routine and continue executing.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to send to the calling co-routine.
     */
    public function return_(StrandInterface $strand, $value = null)
    {
        $coroutine = $strand->current();

        $strand->returnValue($value);

        $strand
            ->kernel()
            ->execute($coroutine);
    }

    /**
     * Throw an exception to the calling co-routine and continue executing.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param Exception       $exception The error to send to the calling co-routine.
     */
    public function throw_(StrandInterface $strand, Exception $exception)
    {
        $coroutine = $strand->current();

        $strand->throwException($exception);

        $strand
            ->kernel()
            ->execute($coroutine);
    }

    /**
     * Terminate execution of the strand.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function terminate(StrandInterface $strand)
    {
        $strand->terminate();
    }

    /**
     * Suspend execution for a specified period of time.
     *
     * @param StrandInterface $strand  The currently executing strand.
     * @param number          $timeout The number of seconds to wait before resuming.
     */
    public function sleep(StrandInterface $strand, $timeout)
    {
        $strand->suspend();

        $strand
            ->kernel()
            ->eventLoop()
            ->addTimer($timeout, [$strand, 'resume']);
    }

    /**
     * Suspend execution of the strand.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function suspend(StrandInterface $strand, callable $callback)
    {
        $strand->suspend();

        $callback($strand);
    }

    /**
     * Suspend the strand until the next tick.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function cooperate(StrandInterface $strand)
    {
        $strand->nextTickDeferred();
    }

    /**
     * Resume the strand immediately.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function noop(StrandInterface $strand)
    {
        $strand->nextTickImmediate();
    }
}

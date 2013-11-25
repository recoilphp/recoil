<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Kernel\Exception\StrandTerminatedException;
use Icecave\Recoil\Kernel\Exception\TimeoutException;

class KernelApi implements KernelApiInterface
{
    /**
     * Get the strand the co-routine is executing on.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function strand(StrandInterface $strand)
    {
        $strand->resume($strand);
    }

    /**
     * Return a value to the calling co-routine.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to send to the calling co-routine.
     */
    public function return_(StrandInterface $strand, $value = null)
    {
        $strand->returnValue($value);
    }

    /**
     * Throw an exception to the calling co-routine.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param Exception       $exception The error to send to the calling co-routine.
     */
    public function throw_(StrandInterface $strand, Exception $exception)
    {
        $strand->throwException($exception);
    }

    /**
     * Return a value to the calling co-routine, and optionally continue executing.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to send to the calling co-routine.
     */
    public function returnAndResume(StrandInterface $strand, $value = null)
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
    public function throwAndResume(StrandInterface $strand, Exception $exception)
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

        $timer = $strand
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
     * Execute a co-routine with a time limit.
     *
     * If the co-routine does not complete within the specified time it is
     * cancelled.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param number          $timeout   The number of seconds to wait before cancelling.
     * @param mixed           $coroutine The coroutine to execute.
     */
    public function timeout(StrandInterface $strand, $timeout, $coroutine)
    {
        // Suspend the current strand ...
        $strand->suspend();

        // Create a new strand to execute the coroutine ...
        $substrand = $strand
            ->kernel()
            ->execute($coroutine);

        // Set up a timer to terminate the new strand after the timeout period
        // has elapsed.
        $timer = $strand
            ->kernel()
            ->eventLoop()
            ->addTimer(
                $timeout,
                function ($timer) use ($strand, $substrand) {
                    $strand->resumeWithException(new TimeoutException);
                    $substrand->terminate();
                }
            );

        // Cancel the timeout timer if the substrand completes before it is due.
        $substrand->then(
            function ($value) use ($strand, $timer) {
                $timer->cancel();
                $strand->resume($value);
            },
            function ($exception) use ($strand, $timer) {
                $timer->cancel();

                // Some non-timeout error has occurred ...
                if (!$exception instanceof StrandTerminatedException) {
                    $strand->resumeWithException($exception);

                // The substrand was terminated, but not by the time-out ...
                } elseif ($timer->isActive()) {
                    $strand->terminate();
                }
            }
        );
    }

    /**
     * Suspend the strand until the next tick.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function cooperate(StrandInterface $strand)
    {
        $strand->suspend();

        $strand
            ->kernel()
            ->eventLoop()
            ->nextTick([$strand, 'resume']);
    }

    /**
     * Resume the strand immediately.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function noop(StrandInterface $strand)
    {
    }
}

<?php
namespace Icecave\Recoil\Kernel\Strand;

use Exception;
use Icecave\Recoil\Coroutine\AbstractCoroutine;

/**
 * The base co-routine in a strand's call-stack.
 */
class StackBase extends AbstractCoroutine
{
    /**
     * Invoked when tick() is called for the first time.
     *
     * @codeCoverageIgnore
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function call(StrandInterface $strand)
    {
        throw new Exception('Not supported.');
    }

    /**
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     * @param mixed           $value  The value passed to sendOnNextTick().
     */
    public function resumeWithValue(StrandInterface $strand, $value)
    {
        $strand->emit('success', [$strand, $value]);
        $strand->emit('exit', [$strand]);
        $strand->removeAllListeners();

        $strand->pop();
        $strand->suspend();
    }

    /**
     * Invoked when tick() is called after throwOnNextTick().
     *
     * @param StrandInterface $strand    The strand that is executing the co-routine.
     * @param Exception       $exception The exception passed to throwOnNextTick().
     */
    public function resumeWithException(StrandInterface $strand, Exception $exception)
    {
        $throwException = true;

        $preventDefault = function () use (&$throwException) {
            $throwException = false;
        };

        $strand->emit('error', [$strand, $exception, $preventDefault]);
        $strand->emit('exit', [$strand]);
        $strand->removeAllListeners();

        $strand->pop();
        $strand->suspend();

        if ($throwException) {
            throw $exception;
        }
    }

    /**
     * Invoked when tick() is called after terminateOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function terminate(StrandInterface $strand)
    {
        $strand->emit('terminate', [$strand]);
        $strand->emit('exit', [$strand]);
        $strand->removeAllListeners();

        $strand->pop();
        $strand->suspend();
    }
}

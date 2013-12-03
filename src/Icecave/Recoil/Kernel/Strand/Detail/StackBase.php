<?php
namespace Icecave\Recoil\Kernel\Strand\Detail;

use Exception;
use Icecave\Recoil\Coroutine\AbstractCoroutine;
use Icecave\Recoil\Kernel\Strand\StrandInterface;

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
        $strand->pop();
        $strand->suspend();

        $strand
            ->resultHandler()
            ->handleResult($strand, new ValueResult($value));
    }

    /**
     * Invoked when tick() is called after throwOnNextTick().
     *
     * @param StrandInterface $strand    The strand that is executing the co-routine.
     * @param Exception       $exception The exception passed to throwOnNextTick().
     */
    public function resumeWithException(StrandInterface $strand, Exception $exception)
    {
        $strand->pop();
        $strand->suspend();

        $strand
            ->resultHandler()
            ->handleResult($strand, new ExceptionResult($exception));
    }

    /**
     * Invoked when tick() is called after terminateOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function terminate(StrandInterface $strand)
    {
        $strand->pop();
        $strand->suspend();

        $strand
            ->resultHandler()
            ->handleResult($strand, new TerminatedResult);
    }
}

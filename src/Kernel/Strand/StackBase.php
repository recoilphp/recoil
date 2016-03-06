<?php

namespace Recoil\Kernel\Strand;

use Exception;
use LogicException;
use Recoil\Coroutine\Coroutine;
use Recoil\Coroutine\CoroutineTrait;

/**
 * The base coroutine in a strand's call-stack.
 *
 * @access private
 */
class StackBase implements Coroutine
{
    use CoroutineTrait;

    /**
     * Start the coroutine.
     *
     * @codeCoverageIgnore
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function call(Strand $strand)
    {
        throw new LogicException('Not supported.');
    }

    /**
     * Resume execution of a suspended coroutine by passing it a value.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     * @param mixed  $value  The value to send to the coroutine.
     */
    public function resumeWithValue(Strand $strand, $value)
    {
        $strand->emit('success', [$strand, $value]);
        $strand->emit('exit', [$strand]);
        $strand->removeAllListeners();

        $strand->pop();
        $strand->suspend();
    }

    /**
     * Resume execution of a suspended coroutine by passing it an exception.
     *
     * @param Strand    $strand    The strand that is executing the coroutine.
     * @param Exception $exception The exception to send to the coroutine.
     */
    public function resumeWithException(Strand $strand, Exception $exception)
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
     * Inform the coroutine that the executing strand is being terminated.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function terminate(Strand $strand)
    {
        $strand->emit('terminate', [$strand]);
        $strand->emit('exit', [$strand]);
        $strand->removeAllListeners();

        $strand->suspend();
    }
}

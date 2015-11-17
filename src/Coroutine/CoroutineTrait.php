<?php

namespace Recoil\Coroutine;

use Exception;
use Recoil\Kernel\Strand\Strand;

/**
 * A trait for coroutines that provides basic default implementations.
 */
trait CoroutineTrait
{
    /**
     * Start the coroutine.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    abstract public function call(Strand $strand);

    /**
     * Resume execution of a suspended coroutine by passing it a value.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     * @param mixed  $value  The value to send to the coroutine.
     */
    public function resumeWithValue(Strand $strand, $value)
    {
        $strand->returnValue($value);
    }

    /**
     * Resume execution of a suspended coroutine by passing it an exception.
     *
     * @param Strand    $strand    The strand that is executing the coroutine.
     * @param Exception $exception The exception to send to the coroutine.
     */
    public function resumeWithException(Strand $strand, Exception $exception)
    {
        $strand->throwException($exception);
    }

    /**
     * Inform the coroutine that the executing strand is being terminated.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function terminate(Strand $strand)
    {
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
    }
}

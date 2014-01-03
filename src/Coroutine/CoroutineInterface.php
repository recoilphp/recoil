<?php
namespace Recoil\Coroutine;

use Exception;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * A coroutine represents a unit of work that can be suspended and resumed.
 */
interface CoroutineInterface
{
    /**
     * Execute the next unit of work.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function tick(StrandInterface $strand);

    /**
     * Store a value to send to the coroutine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function sendOnNextTick($value);

    /**
     * Store an exception to send to the coroutine on the next tick.
     *
     * @param Exception $exception The exception to send.
     */
    public function throwOnNextTick(Exception $exception);

    /**
     * Instruct the coroutine to terminate execution on the next tick.
     */
    public function terminateOnNextTick();
}

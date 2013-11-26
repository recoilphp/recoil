<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Icecave\Recoil\Kernel\Strand\StrandInterface;

/**
 * A co-routine represents a unit of work that can be suspended and resumed.
 */
interface CoroutineInterface
{
    /**
     * Execute the next unit of work.
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function tick(StrandInterface $strand);

    /**
     * Store a value to send to the co-routine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function sendOnNextTick($value);

    /**
     * Store an exception to send to the co-routine on the next tick.
     *
     * @param Exception $exception The exception to send.
     */
    public function throwOnNextTick(Exception $exception);

    /**
     * Instruct the co-routine to terminate execution on the next tick.
     */
    public function terminateOnNextTick();
}

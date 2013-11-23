<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Icecave\Recoil\Kernel\StrandInterface;

/**
 * A resumable sub-routine.
 */
interface CoroutineInterface
{
    /**
     * Perform the next unit-of-work.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function tick(StrandInterface $strand);

    /**
     * Set the value to send to the co-routine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function setValue($value);

    /**
     * Set the exception to throw to the co-routine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function setException(Exception $exception);

    /**
     * Cancel execution of the co-routine.
     */
    public function cancel();
}

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
     * @param StrandInterface $strand    The currently executing strand.
     * @param mixed           $value
     * @param Exception|null  $exception
     */
    public function tick(StrandInterface $strand, $value = null, Exception $exception = null);

    /**
     * Cancel execution of the co-routine.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function cancel(StrandInterface $strand);
}

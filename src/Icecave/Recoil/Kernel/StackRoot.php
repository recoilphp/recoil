<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Coroutine\CoroutineInterface;
use LogicException;

/**
 * The root co-routine in the stack of each strand.
 *
 * This co-routine is responsible for propagating un-caught exceptions in a
 * strand.
 */
class StackRoot implements CoroutineInterface
{
    /**
     * Perform the next unit-of-work.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param mixed           $value
     * @param Exception|null  $exception
     */
    public function tick(StrandInterface $strand, $value = null, Exception $exception = null)
    {
        if ($exception) {
            throw $exception;
        }

        $strand->returnValue($value);
    }

    /**
     * Cancel execution of the co-routine.
     *
     * @codeCoverageIgnore
     */
    public function cancel()
    {
        throw new LogicException('Not supported.');
    }

    private $exception;
}

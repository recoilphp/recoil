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
     * @param StrandInterface $strand The currently executing strand.
     */
    public function tick(StrandInterface $strand)
    {
        if ($this->exception) {
            throw $this->exception;
        }

        $strand->pop();
    }

    /**
     * Set the value to send to the co-routine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function setValue($value)
    {
        $this->exception = null;
    }

    /**
     * Set the exception to throw to the co-routine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function setException(Exception $exception)
    {
        $this->exception = $exception;
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

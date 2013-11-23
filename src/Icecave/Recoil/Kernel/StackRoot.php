<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Coroutine\CoroutineInterface;
use LogicException;

class StackRoot implements CoroutineInterface
{
    public function tick(StrandInterface $strand)
    {
        if ($this->exception) {
            throw $this->exception;
        }

        $strand->pop();
    }

    public function setValue($value)
    {
        $this->exception = null;
    }

    public function setException(Exception $exception)
    {
        $this->exception = $exception;
    }

    /**
     * @codeCoverageIgnore
     */
    public function cancel()
    {
        throw new LogicException('Not supported.');
    }

    private $exception;
}

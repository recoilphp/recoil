<?php
namespace Icecave\Recoil\Kernel;

use Exception;

class KernelApi implements KernelApiInterface
{
    public function return_(StrandInterface $strand, $value = null)
    {
        $coroutine = $strand->current();

        $strand->returnValue($value);

        $strand->kernel()->execute($coroutine);
    }

    public function throw_(StrandInterface $strand, Exception $exception)
    {
        $coroutine = $strand->current();

        $strand->throwException($exception);

        $strand->kernel()->execute($coroutine);
    }

    public function terminate(StrandInterface $strand)
    {
        $strand->terminate();
    }

    public function suspend(StrandInterface $strand, callable $callback)
    {
        $strand->suspend();

        $callback($strand);
    }

    public function cooperate(StrandInterface $strand)
    {
    }
}

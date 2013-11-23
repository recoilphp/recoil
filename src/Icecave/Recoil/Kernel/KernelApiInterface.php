<?php
namespace Icecave\Recoil\Kernel;

use Exception;

interface KernelApiInterface
{
    public function return_(StrandInterface $strand, $value = null);

    public function throw_(StrandInterface $strand, Exception $exception);

    public function terminate(StrandInterface $strand);

    public function suspend(StrandInterface $strand, callable $callback);

    public function cooperate(StrandInterface $strand);
}

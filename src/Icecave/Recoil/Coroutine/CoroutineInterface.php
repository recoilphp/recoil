<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Icecave\Recoil\Kernel\StrandInterface;

interface CoroutineInterface
{
    public function tick(StrandInterface $strand);

    public function setValue($value);

    public function setException(Exception $exception);

    public function cancel();
}

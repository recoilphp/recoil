<?php
namespace Icecave\Recoil\Coroutine;

use Icecave\Recoil\Kernel\StrandInterface;

/**
 * Adapts arbitrary values into co-routine objects.
 */
interface CoroutineAdaptorInterface
{
    public function adapt(StrandInterface $strand, $value);
}

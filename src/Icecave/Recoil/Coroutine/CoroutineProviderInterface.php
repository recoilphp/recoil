<?php
namespace Icecave\Recoil\Coroutine;

use Icecave\Recoil\Kernel\Strand\StrandInterface;

/**
 * A co-routine provide is an object that can produce an object that can be
 * adapted into a co-routine using the kernel's co-routine adaptor.
 */
interface CoroutineProviderInterface
{
    /**
     * Produce a co-routine.
     *
     * @param StrandInterface $strand The strand that will execute the co-routine.
     *
     * @return mixed
     */
    public function coroutine(StrandInterface $strand);
}

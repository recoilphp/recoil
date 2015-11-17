<?php

namespace Recoil\Coroutine;

use Recoil\Kernel\Strand\StrandInterface;

/**
 * A coroutine provide is an object that can produce an object that can be
 * adapted into a coroutine using the kernel's coroutine adaptor.
 */
interface CoroutineProviderInterface
{
    /**
     * Produce a coroutine.
     *
     * @param StrandInterface $strand The strand that will execute the coroutine.
     *
     * @return mixed
     */
    public function coroutine(StrandInterface $strand);
}

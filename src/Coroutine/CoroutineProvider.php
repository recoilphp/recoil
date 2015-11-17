<?php

namespace Recoil\Coroutine;

use Recoil\Kernel\Strand\Strand;

/**
 * A coroutine provide is an object that can produce an object that can be
 * adapted into a coroutine using the kernel's coroutine adaptor.
 */
interface CoroutineProvider
{
    /**
     * Produce a coroutine.
     *
     * @param Strand $strand The strand that will execute the coroutine.
     *
     * @return mixed
     */
    public function coroutine(Strand $strand);
}

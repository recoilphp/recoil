<?php

namespace Recoil\Coroutine;

use InvalidArgumentException;
use Recoil\Kernel\Strand\Strand;

/**
 * Adapts arbitrary values into coroutine objects.
 */
interface CoroutineAdaptor
{
    /**
     * Adapt a value into a coroutine.
     *
     * @param Strand $strand The currently executing strand.
     * @param mixed  $value  The value to adapt.
     *
     * @return Coroutine
     * @throws InvalidArgumentException if now valid adaptation can be made.
     */
    public function adapt(Strand $strand, $value);
}

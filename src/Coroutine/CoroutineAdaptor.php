<?php

namespace Recoil\Coroutine;

use InvalidArgumentException;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * Adapts arbitrary values into coroutine objects.
 */
interface CoroutineAdaptor
{
    /**
     * Adapt a value into a coroutine.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to adapt.
     *
     * @return Coroutine
     * @throws InvalidArgumentException if now valid adaptation can be made.
     */
    public function adapt(StrandInterface $strand, $value);
}

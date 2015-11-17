<?php

namespace Recoil\Coroutine;

use InvalidArgumentException;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * Adapts arbitrary values into coroutine objects.
 */
interface CoroutineAdaptorInterface
{
    /**
     * Adapt a value into a coroutine.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to adapt.
     *
     * @return CoroutineInterface
     * @throws InvalidArgumentException if now valid adaptation can be made.
     */
    public function adapt(StrandInterface $strand, $value);
}

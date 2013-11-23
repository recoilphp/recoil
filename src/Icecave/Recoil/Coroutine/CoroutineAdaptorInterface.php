<?php
namespace Icecave\Recoil\Coroutine;

use Icecave\Recoil\Kernel\StrandInterface;
use InvalidArgumentException;

/**
 * Adapts arbitrary values into co-routine objects.
 */
interface CoroutineAdaptorInterface
{
    /**
     * Adapt a value into a co-routine.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to adapt.
     *
     * @return CoroutineInterface
     * @throws InvalidArgumentException if now valid adaptation can be made.
     */
    public function adapt(StrandInterface $strand, $value);
}

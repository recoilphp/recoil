<?php
namespace Icecave\Recoil\Coroutine;

use Generator;
use Icecave\Recoil\Kernel\StrandInterface;
use Icecave\Recoil\Recoil;
use Icecave\Repr\Repr;
use InvalidArgumentException;
use React\Promise\PromiseInterface;

/**
 * The default co-routine adaptor implementation.
 */
class CoroutineAdaptor implements CoroutineAdaptorInterface
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
    public function adapt(StrandInterface $strand, $value)
    {
        if ($value instanceof CoroutineInterface) {
            return $value;
        } elseif ($value instanceof Generator) {
            return new GeneratorCoroutine($value);
        } elseif ($value instanceof PromiseInterface) {
            return new PromiseCoroutine($value);
        } elseif (null === $value) {
            return Recoil::cooperate();
        }

        throw new InvalidArgumentException(
            'Unable to adapt ' . Repr::repr($value) . ' into a co-routine.'
        );
    }
}

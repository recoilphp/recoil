<?php

namespace Recoil\Coroutine;

use Generator;
use Icecave\Repr\Repr;
use InvalidArgumentException;
use React\Promise\PromiseInterface;
use Recoil\Kernel\Strand\Strand;
use Recoil\Recoil;

/**
 * The default coroutine adaptor implementation.
 */
class StandardCoroutineAdaptor implements CoroutineAdaptor
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
    public function adapt(Strand $strand, $value)
    {
        while ($value instanceof CoroutineProvider) {
            $value = $value->coroutine($strand);
        }

        if ($value instanceof Coroutine) {
            return $value;
        } elseif ($value instanceof Generator) {
            return new GeneratorCoroutine($value);
        } elseif ($value instanceof PromiseInterface) {
            return new PromiseCoroutine($value);
        } elseif (is_array($value)) {
            return Recoil::all($value);
        } elseif (null === $value) {
            return Recoil::cooperate();
        }

        throw new InvalidArgumentException(
            'Unable to adapt ' . Repr::repr($value) . ' into a coroutine.'
        );
    }
}

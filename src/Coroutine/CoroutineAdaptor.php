<?php
namespace Recoil\Coroutine;

use Generator;
use Recoil\Kernel\Strand\StrandInterface;
use Recoil\Recoil;
use Icecave\Repr\Repr;
use InvalidArgumentException;
use React\Promise\PromiseInterface;

/**
 * The default coroutine adaptor implementation.
 */
class CoroutineAdaptor implements CoroutineAdaptorInterface
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
    public function adapt(StrandInterface $strand, $value)
    {
        while ($value instanceof CoroutineProviderInterface) {
            $value = $value->coroutine($strand);
        }

        if ($value instanceof CoroutineInterface) {
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

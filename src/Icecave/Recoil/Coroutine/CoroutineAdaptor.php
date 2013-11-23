<?php
namespace Icecave\Recoil\Coroutine;

use Generator;
use Icecave\Recoil\Kernel\StrandInterface;
use Icecave\Recoil\Recoil;
use Icecave\Repr\Repr;
use InvalidArgumentException;

class CoroutineAdaptor implements CoroutineAdaptorInterface
{
    public function adapt(StrandInterface $strand, $value)
    {
        if ($value instanceof CoroutineInterface) {
            return $value;
        } elseif ($value instanceof Generator) {
            return new GeneratorCoroutine($value);
        } elseif (null === $value) {
            return Recoil::cooperate();
        }

        throw new InvalidArgumentException(
            'Unable to adapt ' . Repr::repr($value) . ' into a co-routine.'
        );
    }
}

<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Coroutine\CoroutineInterface;
use LogicException;
use React\Promise\ResolverInterface;

/**
 * The base co-routine in a strand's call-stack.
 */
class StackBase implements CoroutineInterface
{
    public function __construct(ResolverInterface $resolver)
    {
        $this->resolver = $resolver;
        $this->propagateExceptions = true;
    }

    /**
     * Perform the next unit-of-work.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param mixed           $value
     * @param Exception|null  $exception
     */
    public function tick(StrandInterface $strand, $value = null, Exception $exception = null)
    {
        $strand->terminate();

        if ($exception) {
            $this->resolver->reject($exception);

            if ($this->propagateExceptions) {
                throw $exception;
            }
        } else {
            $this->resolver->resolve($value);
        }
    }

    /**
     * Cancel execution of the co-routine.
     *
     * @codeCoverageIgnore
     */
    public function cancel()
    {
        throw new LogicException('Not supported.');
    }

    public function disableExceptionPropagation()
    {
        $this->propagateExceptions = false;
    }

    private $resolver;
    private $propagateExceptions;
}

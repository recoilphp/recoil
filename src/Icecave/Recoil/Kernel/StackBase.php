<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Coroutine\CoroutineInterface;
use Icecave\Recoil\Kernel\Exception\StrandTerminatedException;
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
        $strand->pop();
        $strand->suspend();

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
     * @param StrandInterface $strand The currently executing strand.
     */
    public function cancel(StrandInterface $strand)
    {
        $strand->pop();
        $strand->suspend();

        $this->resolver->reject(new StrandTerminatedException);
    }

    public function disableExceptionPropagation()
    {
        $this->propagateExceptions = false;
    }

    private $resolver;
    private $propagateExceptions;
}

<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Icecave\Recoil\Kernel\StrandInterface;
use React\Promise\ResolverInterface;

/**
 * A co-routine that resolves a promise when it is resumed.
 */
class ResolverCoroutine implements CoroutineInterface
{
    /**
     * @param ResolverInterface $resolver  The ReactPHP promise resolver.
     * @param mixed             $coroutine
     */
    public function __construct(ResolverInterface $resolver, $coroutine)
    {
        $this->resolver = $resolver;
        $this->coroutine = $coroutine;
        $this->pending = true;
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
        if ($this->pending) {
            $this->pending = false;
            $strand->call($this->coroutine);
        } elseif ($exception) {
            $this->resolver->reject($exception);
            $strand->returnValue(false);
        } else {
            $this->resolver->resolve($value);
            $strand->returnValue(true);
        }
    }

    /**
     * Cancel execution of the co-routine.
     */
    public function cancel()
    {
        $this->resolver = null;
        $this->pending = false;
    }

    private $resolver;
    private $coroutine;
    private $pending;
}

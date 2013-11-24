<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Icecave\Recoil\Kernel\StrandInterface;
use React\Promise\ResolverInterface;
use RuntimeException;

/**
 * A co-routine that resolves a promise when it is resumed.
 */
class ResolverCoroutine implements CoroutineInterface
{
    /**
     * @param ResolverInterface $resolver The ReactPHP promise resolver.
     * @param mixed $coroutine
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
     * @param StrandInterface $strand The currently executing strand.
     */
    public function tick(StrandInterface $strand)
    {
        if ($this->pending) {
            $this->pending = false;
            $strand->call($this->coroutine);
        } elseif ($this->exception) {
            $this->resolver->reject($this->exception);
            $strand->returnValue(false);
        } else {
            $this->resolver->resolve($this->value);
            $strand->returnValue(true);
        }
    }

    /**
     * Set the value to send to the co-routine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function setValue($value)
    {
        $this->value = $value;
        $this->exception = null;
    }

    /**
     * Set the exception to throw to the co-routine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function setException(Exception $exception)
    {
        $this->value = null;
        $this->exception = $exception;
    }

    /**
     * Cancel execution of the co-routine.
     */
    public function cancel()
    {
        $this->resolver = null;
        $this->pending = false;
        $this->value = null;
        $this->exception = null;
    }

    private $resolver;
    private $coroutine;
    private $pending;
    private $value;
    private $exception;
}

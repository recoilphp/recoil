<?php
namespace Icecave\Recoil\Kernel\Strand;

use Exception;
use Icecave\Recoil\Coroutine\AbstractCoroutine;
use Icecave\Recoil\Kernel\Exception\StrandTerminatedException;
use React\Promise\ResolverInterface;

/**
 * The base co-routine in a strand's call-stack.
 */
class StackBase extends AbstractCoroutine
{
    public function __construct(ResolverInterface $resolver)
    {
        $this->resolver = $resolver;
        $this->suppressExceptions = false;

        parent::__construct();
    }

    public function call(StrandInterface $strand)
    {
        $strand->pop();
        $strand->suspend();
    }

    public function resume(StrandInterface $strand, $value)
    {
        $strand->pop();
        $strand->suspend();

        $this->resolver->resolve($value);
    }

    public function error(StrandInterface $strand, Exception $exception)
    {
        $strand->pop();
        $strand->suspend();

        $this->resolver->reject($exception);

        if (!$this->suppressExceptions) {
            $strand
                ->kernel()
                ->exceptionHandler()
                ->handleException($strand, $exception);
        }
    }

    public function terminate(StrandInterface $strand)
    {
        $strand->pop();
        $strand->suspend();

        $this->resolver->reject(new StrandTerminatedException);
    }

    public function suppressExceptions()
    {
        $this->suppressExceptions = true;
    }

    private $resolver;
    private $suppressExceptions;
}

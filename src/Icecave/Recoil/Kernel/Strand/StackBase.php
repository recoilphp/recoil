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

    /**
     * Invoked when tick() is called for the first time.
     *
     * @codeCoverageIgnore
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function call(StrandInterface $strand)
    {
        throw new Exception('Not supported.');
    }

    /**
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     * @param mixed           $value  The value passed to sendOnNextTick().
     */
    public function resumeWithValue(StrandInterface $strand, $value)
    {
        $strand->pop();
        $strand->suspend();

        $this->resolver->resolve($value);
    }

    /**
     * Invoked when tick() is called after throwOnNextTick().
     *
     * @param StrandInterface $strand    The strand that is executing the co-routine.
     * @param Exception       $exception The exception passed to throwOnNextTick().
     */
    public function resumeWithException(StrandInterface $strand, Exception $exception)
    {
        $strand->pop();
        $strand->suspend();

        $this->resolver->reject($exception);

        if (!$this->suppressExceptions) {
            $strand
                ->kernel()
                ->exceptionHandler()
                ->handleException($strand, $exception);
        // @codeCoverageIgnoreStart
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Invoked when tick() is called after terminateOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function terminate(StrandInterface $strand)
    {
        $strand->pop();
        $strand->suspend();

        $this->resolver->reject(new StrandTerminatedException);
    }

    /**
     * Prevent exceptions from being sent to the kernel's exception handler.
     */
    public function suppressExceptions()
    {
        $this->suppressExceptions = true;
    }

    private $resolver;
    private $suppressExceptions;
}

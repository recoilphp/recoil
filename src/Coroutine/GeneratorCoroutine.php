<?php
namespace Recoil\Coroutine;

use Exception;
use Generator;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * A coroutine wrapper for PHP generators.
 */
class GeneratorCoroutine implements CoroutineInterface
{
    use CoroutineTrait;

    /**
     * @param Generator $generator The PHP generator that implements the coroutine logic.
     */
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
        $this->finalizeCallbacks = [];
    }

    /**
     * Invoked when tick() is called for the first time.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function call(StrandInterface $strand)
    {
        try {
            $e = null;
            $valid = $this->generator->valid();
        } catch (Exception $e) {
            $strand->throwException($e);

            return;
        }

        if ($valid) {
            $strand->call(
                $this->generator->current()
            );
        } else {
            $strand->returnValue(null);
        }
    }

    /**
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     * @param mixed           $value  The value passed to sendOnNextTick().
     */
    public function resumeWithValue(StrandInterface $strand, $value)
    {
        try {
            $e = null;
            $this->generator->send($value);
            $valid = $this->generator->valid();
        } catch (Exception $e) {
            $strand->throwException($e);

            return;
        }

        if ($valid) {
            $strand->call(
                $this->generator->current()
            );
        } else {
            $strand->returnValue(null);
        }
    }

    /**
     * Resume execution of a suspended coroutine by passing it an exception.
     *
     * @param StrandInterface $strand    The strand that is executing the coroutine.
     * @param Exception       $exception The exception to send to the coroutine.
     */
    public function resumeWithException(StrandInterface $strand, Exception $exception)
    {
        try {
            $e = null;
            $this->generator->throw($exception);
            $valid = $this->generator->valid();
        } catch (Exception $e) {
            $strand->throwException($e);

            return;
        }

        if ($valid) {
            $strand->call(
                $this->generator->current()
            );
        } else {
            $strand->returnValue(null);
        }
    }

    /**
     * Finalize the coroutine.
     *
     * This method is invoked after the coroutine is popped from the call stack.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function finalize(StrandInterface $strand)
    {
        $this->generator = null;

        foreach ($this->finalizeCallbacks as $callback) {
            $callback($strand, $this);
        }

        $this->finalizeCallbacks = [];
    }

    /**
     * Register a callback to be invoked when the co-routine is finalized.
     *
     * @internal
     *
     * @param callable $callback The callback to invoke.
     */
    public function registerFinalizeCallback(callable $callback) {
        $this->finalizeCallbacks[] = $callback;
    }

    private $generator;
    private $finalizeCallbacks;
}

<?php

namespace Recoil\Coroutine;

use Exception;
use Generator;
use Recoil\Kernel\Strand\Strand;

/**
 * A coroutine wrapper for PHP generators.
 */
class GeneratorCoroutine implements Coroutine
{
    use CoroutineTrait;

    /**
     * @param Generator $generator The PHP generator that implements the coroutine logic.
     */
    public function __construct(Generator $generator)
    {
        if (null === self::$hasReturnValue) {
            self::$hasReturnValue = method_exists(Generator::class, 'getReturn');
        }

        $this->generator         = $generator;
        $this->finalizeCallbacks = [];
    }

    /**
     * Invoked when tick() is called for the first time.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function call(Strand $strand)
    {
        try {
            $valid = $this->generator->valid();
        } catch (Exception $e) {
            $strand->throwException($e);

            return;
        }

        if ($valid) {
            $strand->call(
                $this->generator->current()
            );
        } elseif (self::$hasReturnValue) {
            $strand->returnValue($this->generator->getReturn());
        } else {
            $strand->returnValue(null);
        }
    }

    /**
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param Strand $strand The strand that is executing the coroutine.
     * @param mixed  $value  The value passed to sendOnNextTick().
     */
    public function resumeWithValue(Strand $strand, $value)
    {
        try {
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
        } elseif (self::$hasReturnValue) {
            $strand->returnValue($this->generator->getReturn());
        } else {
            $strand->returnValue(null);
        }
    }

    /**
     * Resume execution of a suspended coroutine by passing it an exception.
     *
     * @param Strand    $strand    The strand that is executing the coroutine.
     * @param Exception $exception The exception to send to the coroutine.
     */
    public function resumeWithException(Strand $strand, Exception $exception)
    {
        try {
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
        } elseif (self::$hasReturnValue) {
            $strand->returnValue($this->generator->getReturn());
        } else {
            $strand->returnValue(null);
        }
    }

    /**
     * Finalize the coroutine.
     *
     * This method is invoked after the coroutine is popped from the call stack.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function finalize(Strand $strand)
    {
        $this->generator = null;

        foreach ($this->finalizeCallbacks as $callback) {
            $callback($strand, $this);
        }

        $this->finalizeCallbacks = [];
    }

    /**
     * Register a callback to be invoked when the coroutine is finalized.
     *
     * @access private
     *
     * @param callable $callback The callback to invoke.
     */
    public function registerFinalizeCallback(callable $callback)
    {
        $this->finalizeCallbacks[] = $callback;
    }

    private static $hasReturnValue;
    private $generator;
    private $finalizeCallbacks;
}

<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Generator;
use Icecave\Recoil\Kernel\StrandInterface;

/**
 * A co-routine wrapper for PHP generators.
 */
class GeneratorCoroutine implements CoroutineInterface
{
    /**
     * @param Generator $generator The PHP generator that implements the co-routine logic.
     */
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
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
        try {
            // The generator has not been started yet ...
            if ($this->pending) {
                $this->pending = false;

            // The generator is running and there as exception to be sent ...
            } elseif ($exception) {
                $this->generator->throw($exception);

            // Otherwise send the value ...
            } else {
                $this->generator->send($value);
            }

            $valid = $this->generator->valid();

        // An exception was thrown, propagate it to the caller ...
        } catch (Exception $e) {
            $strand->throwException($e);

            return;
        }

        // The generator yielded a co-routine to execute ...
        if ($valid) {
            $strand->call($this->generator->current());

        // There's nothing left to do ...
        } else {
            $strand->returnValue(null);
        }
    }

    /**
     * Cancel execution of the co-routine.
     */
    public function cancel()
    {
        $this->generator = null;
        $this->pending = false;
    }

    private $generator;
    private $pending;
}

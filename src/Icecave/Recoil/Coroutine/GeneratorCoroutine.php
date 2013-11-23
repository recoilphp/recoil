<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Generator;
use Icecave\Recoil\Kernel\StrandInterface;

class GeneratorCoroutine implements CoroutineInterface
{
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
        $this->pending = true;
    }

    public function tick(StrandInterface $strand)
    {
        try {
            // The generator has not been started yet ...
            if ($this->pending) {
                $this->pending = false;

            // The generator is running and there as exception to be sent ...
            } elseif ($this->exception) {
                $this->generator->throw($this->exception);

            // Otherwise send the value ...
            } else {
                $this->generator->send($this->value);
            }

            $valid = $this->generator->valid();

        // An exception was thrown, propagate it to the caller ...
        } catch (Exception $e) {
            $strand->throwException($e);

            return;

        // Always clean up the value/exception ...
        } finally {
            $this->value = null;
            $this->exception = null;
        }

        // The generator yielded a co-routine to execute ...
        if ($valid) {
            $strand->call($this->generator->current());

        // There's nothing left to do ...
        } else {
            $strand->returnValue(null);
        }
    }

    public function setValue($value)
    {
        $this->value = $value;
        $this->exception = null;
    }

    public function setException(Exception $exception)
    {
        $this->value = null;
        $this->exception = $exception;
    }

    public function cancel()
    {
        $this->generator = null;
        $this->value = null;
        $this->exception = null;
    }

    private $generator;
    private $pending;
    private $value;
    private $exception;
}

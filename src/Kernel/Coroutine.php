<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Exception;
use Generator;

/**
 * A coroutine based on a PHP generator.
 */
final class Coroutine implements Awaitable, Suspendable
{
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Perform the work and resume the caller upon completion.
     *
     * @param Strand      $strand The executing strand.
     * @param Suspendable $caller The waiting object.
     * @param Api         $api    The kernel API.
     */
    public function await(Strand $strand, Suspendable $caller, Api $api)
    {
        assert(!$this->strand,   'coroutine already started');
        assert($this->generator, 'coroutine already finished');

        $this->strand = $strand;
        $this->caller = $caller;
        $this->api = $api;
        $this->tick();
    }

    /**
     * Resume execution.
     *
     * @param mixed $result The result.
     */
    public function resume($result = null)
    {
        assert($this->strand,  'coroutine not started');
        assert(!$this->action, 'coroutine not suspended');

        $this->action = 'send';
        $this->result = $result;

        if (!$this->active) {
            $this->tick();
        }
    }

    /**
     * Resume execution with an exception.
     *
     * @param Exception $exception The exception.
     */
    public function throw(Exception $exception)
    {
        assert($this->strand,  'coroutine not started');
        assert(!$this->action, 'coroutine not suspended');

        $this->action = 'throw';
        $this->result = $exception;

        if (!$this->active) {
            $this->tick();
        }
    }

    private function tick()
    {
        assert(!$this->active,   'coroutine currently ticking');
        assert($this->caller,    'coroutine not started');
        assert($this->generator, 'coroutine already finished');

        try {
            $this->active = true;

            if ($this->action) {
                next:
                assert($this->action === 'send' || $this->action === 'throw');
                assert($this->action === 'send' || $this->result instanceof Exception);

                $this->generator->{$this->action}($this->result);
                $this->action = $this->result = null;
            }

            if ($this->generator->valid()) {
                $this->api->__dispatch(
                    DispatchSource::COROUTINE,
                    $this->strand,
                    $this,
                    $this->generator->current(),
                    $this->generator->key()
                );

                if ($this->action) {
                    goto next;
                }

                return;
            }
        } catch (Exception $e) {
            $this->caller->throw($e);
            $this->generator = $this->strand = $this->caller = $this->api = null;

            return;
        } finally {
            $this->active = false;
        }

        $this->caller->resume($this->generator->getReturn());
        $this->generator = $this->strand = $this->caller = $this->api = null;
    }

    /**
     * @var Generator|null The coroutine implementation.
     */
    private $generator;

    /**
     * @var Strand|null The strand the coroutine is executing on.
     */
    private $strand;

    /**
     * @var Suspendable|null The object waiting for this coroutine to complete.
     */
    private $caller;

    /**
     * @var Api|null The kernel API.
     */
    private $api;

    /**
     * @var boolean True if the tick() function is currently executing.
     */
    private $active = false;

    /**
     * @var null|string The next action to perform ('send' or 'throw').
     */
    private $action;

    /**
     * @var mixed The value to pass to the generator via send or throw on the next tick.
     */
    private $result;
}

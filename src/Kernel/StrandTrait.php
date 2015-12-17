<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Throwable;
use Generator;

trait StrandTrait
{
    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    /**
     * Start the strand.
     *
     * @param Generator|callable $coroutine The strand's entry-point.
     */
    public function start($coroutine)
    {
        if  (!$coroutine instanceof Generator) {
            $coroutine = $coroutine();
        }

        assert(!$this->top, 'strand already started');
        assert($coroutine instanceof Generator);

        $this->top = $coroutine;
        $this->tick();
    }

    /**
     * Terminate execution of the strand.
     *
     * If the strand is suspended waiting on an asynchronous operation, that
     * operation is cancelled.
     *
     * The call stack is not unwound, it is simply discarded.
     */
    public function terminate()
    {
        assert(!$this->top, 'strand already started');

        $this->terminated = true;

        if ($this->terminator) {
            $fn = $this->terminator;
            $fn($this);
        }

        $this->top = null;
        $this->stack = [];
        $this->done(new TerminateException(), null);
    }

    /**
     * Resume execution of a suspended strand.
     *
     * @param mixed $value The value to send to the coroutine on the the top of the call stack.
     */
    public function resume($value = null)
    {
        // Ignore resumes after termination, not all asynchronous operations
        // will have meaningful cancel operations and some may attempt to resume
        // the strand after it has been terminated.
        if ($this->terminated) {
            return;
        }

        assert($this->top, 'strand not started');

        $this->terminator = null;
        $this->action = 'send';
        $this->value = $value;

        if (!$this->ticking) {
            $this->tick();
        }
    }

    /**
     * Resume execution of a suspended strand with an error.
     *
     * @param Throwable $exception The exception to send to the coroutine on the top of the call stack.
     */
    public function throw(Throwable $exception)
    {
        // Ignore resumes after termination, not all asynchronous operations
        // will have meaningful cancel operations and some may attempt to resume
        // the strand after it has been terminated.
        if ($this->terminated) {
            return;
        }

        assert($this->top, 'strand not started');

        $this->terminator = null;
        $this->action = 'throw';
        $this->value = $exception;

        if (!$this->ticking) {
            $this->tick();
        }
    }

    private function tick()
    {
        assert(!$this->ticking, 'strand already ticking');

        try {
            $this->ticking = true;

            // Catch exceptions produced by the generator and propagate them up
            // the call stack ...
            try {
                start:
                if ($this->action) {
                    action:
                    $this->top->{$this->action}($this->value);
                    $this->action = $this->value = null;
                }

                next:
                $suspended = $this->top->valid();

                if ($suspended) {
                    $produced = $this->top->current();
                } else {
                    $produced = $this->top->getReturn();
                }

            } catch (Throwable $e) {
                if ($this->depth) {
                    $this->action = 'throw';
                    $this->value = $e;
                    $this->top = $this->stack[--$this->depth];
                    unset($this->stack[$this->depth]);
                    goto action;
                }

                $this->top = null;
                $this->done($e);
            }

            if ($suspended) {
                if ($produced instanceof Generator) {
                    $this->stack[$this->depth++] = $this->top;
                    $this->top = $produced;
                    goto next;
                } elseif ($produced instanceof CoroutineProvider) {
                    $this->stack[$this->depth++] = $this->top;
                    $this->top = $produced->generator();
                    goto next;
                }

                // Catch exceptions produced by the kernel API or the yielded
                // awaitable and return them to the current generator ...
                try {
                    if ($produced instanceof ApiCall) {
                        $terminator = $this->api->{$produced->name}(
                            $this,
                            ...$produced->arguments
                        );
                    } elseif ($produced instanceof Awaitable) {
                        $terminator = $produced->await(
                            $this,
                            $this->api
                        );
                    } elseif ($produced instanceof AwaitableProvider) {
                        $terminator = $produced->awaitable()->await(
                            $this,
                            $this->api
                        );
                    } else {
                        $terminator = $this->api->__dispatch(
                            $this,
                            $this->top->key(),
                            $produced
                        );
                    }

                    // $this->resume() or throw() has already been called ...
                    if ($this->action) {
                        goto action;
                    }

                    $this->terminator = $terminator;
                } catch (Throwable $e) {
                    $this->action = 'throw';
                    $this->value = $e;
                    goto action;
                }
            } elseif ($this->depth) {
                $this->action = 'send';
                $this->value = $produced;
                $this->top = $this->stack[--$this->depth];
                unset($this->stack[$this->depth]);
                goto action;
            } else {
                $this->top = null;
                $this->done(null, $produced);
            }
        } finally {
            $this->ticking = false;
        }
    }

    /**
     * A hook that can be used by the implementation to perform actions upon
     * completion of the strand.
     */
    private function done(Throwable $exception = null, $result = null)
    {
        if ($exception) {
            throw $exception;
        }
    }

    /**
     * @var Api The kernel API.
     */
    private $api;

    /**
     * @var array<Generator> The call stack (except for the top element).
     */
    private $stack = [];

    /**
     * @var integer The size of $this->stack
     */
    private $depth = 0;

    /**
     * @var Generator|null The current top of the call stack.
     */
    private $top;

    /**
     * @var callable|null A callable invoked when the strand is terminated. Used
     *                    to cancel any pending operation that is not executing
     *                    within Recoil.
     */
    private $terminator;

    /**
     * @var boolean True if the strand is currently ticking.
     */
    private $ticking = false;

    /**
     * @var boolean True if the strand has been terminated.
     */
    private $terminated = false;
}

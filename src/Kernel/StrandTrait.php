<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Generator;
use Throwable;

/**
 * The standard strand implementation.
 */
trait StrandTrait
{
    /**
     * @param Kernel $kernel The kernel on which the strand is executing.
     * @param Api    $api    The kernel API used to handle yielded values.
     */
    public function __construct(Kernel $kernel, Api $api)
    {
        $this->kernel = $kernel;
        $this->api = $api;
        $this->observers = [];
    }

    /**
     * @return Kernel The kernel on which the strand is executing.
     */
    public function kernel()
    {
        return $this->kernel;
    }

    /**
     * Add a strand observer.
     *
     * @param StrandObserver $observer
     */
    public function attachObserver(StrandObserver $observer)
    {
        $this->observers[] = $observer;
    }

    /**
     * Remove a strand observer.
     *
     * @param StrandObserver $observer
     */
    public function detachObserver(StrandObserver $observer)
    {
        $index = \array_search($observer, $this->observers, true);

        if (false !== $index) {
            unset($this->observers[$index]);
        }
    }

    /**
     * Start the strand.
     *
     * @param mixed $coroutine The strand's entry-point.
     */
    public function start($coroutine)
    {
        // Strand was terminated before it was even started.
        if ($this->terminated) {
            return;
        }

        assert(!$this->current, 'strand already started');

        if ($coroutine instanceof Generator) {
            $this->current = $coroutine;
        } elseif ($coroutine instanceof CoroutineProvider) {
            $this->current = $coroutine->coroutine();
        } elseif (\is_callable($coroutine)) {
            $this->current = $coroutine();
        } else {
            $this->current = (static function () use ($coroutine) {
                return yield $coroutine;
            })();
        }

        assert($this->current instanceof Generator);
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
        $this->terminated = true;

        if ($this->terminator) {
            $fn = $this->terminator;
            $this->terminator = null;
            $fn($this);
        }

        foreach ($this->observers as $observer) {
            $observer->terminated($this);
        }

        $this->stack = [];
        $this->current = null;
        $this->observers = [];
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

        assert($this->current, 'strand not started');

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

        assert($this->current, 'strand not started');

        $this->terminator = null;
        $this->action = 'throw';
        $this->value = $exception;

        if (!$this->ticking) {
            $this->tick();
        }
    }

    /**
     * Set the strand 'terminator'.
     *
     * The terminator is a function invoked when the strand is terminated. It is
     * used by the kernel API to clean up and pending asynchronous operations.
     *
     * The terminator function is removed without being invoked when the strand
     * is resumed.
     */
    public function setTerminator(callable $fn = null)
    {
        assert(!$fn || !$this->terminator, 'terminator already exists');

        $this->terminator = $fn;
    }

    private function tick()
    {
        assert(!$this->ticking, 'strand already ticking');

        try {
            $this->ticking = true;

            // Catch exceptions produced by the generator and propagate them up
            // the call stack ...
            try {
                if ($this->action) {
                    action:
                    $this->current->{$this->action}($this->value);
                    $this->action = $this->value = null;
                }

                next:
                $suspended = $this->current->valid();

                if ($suspended) {
                    $produced = $this->current->current();
                } else {
                    $produced = $this->current->getReturn();
                }
            } catch (Throwable $e) {
                if ($this->depth) {
                    $current = &$this->stack[--$this->depth];
                    $this->current = $current;
                    $current = null;

                    $this->action = 'throw';
                    $this->value = $e;
                    goto action;
                }

                $this->current = null;

                if (!$this->observers) {
                    throw $e;
                }

                foreach ($this->observers as $observer) {
                    $observer->failure($this, $e);
                }

                $this->observers = [];
            }

            if ($suspended) {
                if ($produced instanceof Generator) {
                    $this->stack[$this->depth++] = $this->current;
                    $this->current = $produced;
                    goto next;
                } elseif ($produced instanceof CoroutineProvider) {
                    $this->stack[$this->depth++] = $this->current;
                    $this->current = $produced->generator();
                    goto next;
                }

                // Catch exceptions produced by the kernel API or the yielded
                // awaitable and return them to the current generator ...
                try {
                    if ($produced instanceof ApiCall) {
                        $this->api->{$produced->name}(
                            $this,
                            ...$produced->arguments
                        );
                    } elseif ($produced instanceof Awaitable) {
                        $produced->await(
                            $this,
                            $this->api
                        );
                    } elseif ($produced instanceof AwaitableProvider) {
                        $produced->awaitable()->await(
                            $this,
                            $this->api
                        );
                    } else {
                        $this->api->__dispatch(
                            $this,
                            $this->current->key(),
                            $produced
                        );
                    }

                    // $this->resume() or throw() has already been called ...
                    if ($this->action) {
                        goto action;
                    }
                } catch (Throwable $e) {
                    $this->action = 'throw';
                    $this->value = $e;
                    goto action;
                }
            } elseif ($this->depth) {
                $current = &$this->stack[--$this->depth];
                $this->current = $current;
                $current = null;

                $this->action = 'send';
                $this->value = $produced;
                goto action;
            } else {
                $this->current = null;

                foreach ($this->observers as $observer) {
                    $observer->success($this, $produced);
                }
            }
        } finally {
            $this->ticking = false;
        }
    }

    /**
     * @var Kernel The kernel.
     */
    private $kernel;

    /**
     * @var Api The kernel API.
     */
    private $api;

    /**
     * @var array<Generator> The call stack (except for the top element).
     */
    private $stack = [];

    /**
     * @var integer The call stack depth (not including the top element).
     */
    private $depth = 0;

    /**
     * @var Generator|null The current top of the call stack.
     */
    private $current;

    /**
     * @var callable|null A callable invoked when the strand is terminated.
     */
    private $terminator;

    /**
     * @var boolean True if the strand has been terminated.
     */
    private $terminated = false;

    /**
     * @var boolean True if the strand is currently ticking.
     */
    private $ticking = false;

    /**
     * @var array<StrandObserver> The objects observing this strand.
     */
    private $observers = [];
}

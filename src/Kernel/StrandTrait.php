<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Closure;
use Generator;
use InvalidArgumentException;
use Throwable;

/**
 * The standard strand implementation.
 */
trait StrandTrait
{
    /**
     * @param int    $id     The strand ID.
     * @param Kernel $kernel The kernel on which the strand is executing.
     * @param Api    $api    The kernel API used to handle yielded values.
     */
    public function __construct(int $id, Kernel $kernel, Api $api)
    {
        $this->id = $id;
        $this->kernel = $kernel;
        $this->api = $api;
        $this->observers = [];
    }

    /**
     * Get the strand's ID.
     *
     * No two active on the same kernel may share an ID.
     *
     * @return int The strand ID.
     */
    public function id() : int
    {
        return $this->id;
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
        assert($this->state < StrandState::SUCCESS, 'strand has already exited');

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
        // Strand was terminated before it was even started ...
        if ($this->state === StrandState::TERMINATED) {
            return;
        }

        assert($this->state === StrandState::READY, 'strand already started');

        if ($coroutine instanceof Generator) {
            $this->current = $coroutine;
        } elseif ($coroutine instanceof CoroutineProvider) {
            $this->current = $coroutine->coroutine();
        } elseif (
            $coroutine instanceof Closure || // perf
            \is_callable($coroutine)
        ) {
            $this->current = $coroutine();

            if (!$this->current instanceof Generator) {
                throw new InvalidArgumentException(
                    'Callable must return a generator.'
                );
            }
        } else {
            $this->current = (static function () use ($coroutine) {
                return yield $coroutine;
            })();
        }

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
        if ($this->state === StrandState::TERMINATED) {
            return;
        }

        $this->state = StrandState::TERMINATED;

        if ($this->terminator) {
            ($this->terminator)($this);
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
        if ($this->state === StrandState::TERMINATED) {
            return;
        }

        assert(
            $this->state === StrandState::TICKING ||
            $this->state === StrandState::SUSPENDED,
            'strand state does not allow resume'
        );

        $this->terminator = null;
        $this->action = 'send';
        $this->value = $value;

        if ($this->state !== StrandState::TICKING) {
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
        if ($this->state === StrandState::TERMINATED) {
            return;
        }

        assert(
            $this->state === StrandState::TICKING ||
            $this->state === StrandState::SUSPENDED,
            'strand state does not allow resume'
        );

        $this->terminator = null;
        $this->action = 'throw';
        $this->value = $exception;

        if ($this->state !== StrandState::TICKING) {
            $this->tick();
        }
    }

    /**
     * Set the strand 'terminator'.
     *
     * The terminator is a function invoked when the strand is terminated. It is
     * used by the kernel API to clean up any pending asynchronous operations.
     *
     * The terminator function is removed without being invoked when the strand
     * is resumed.
     */
    public function setTerminator(callable $fn = null)
    {
        assert(!$fn || !$this->terminator, 'terminator already exists');
        assert($this->state !== StrandState::TERMINATED, 'strand already terminated');

        $this->terminator = $fn;
    }

    public function awaitable() : Awaitable
    {
        return new StrandWaitOne($this);
    }

    private function tick()
    {
        assert($this->state !== StrandState::TICKING, 'strand already ticking');
        assert($this->current instanceof Generator, 'empty call stack / invalid generator');

        $this->state = StrandState::TICKING;

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
            $this->state = StrandState::FAILED;

            if (!$this->observers) {
                throw $e;
            }

            foreach ($this->observers as $observer) {
                $observer->failure($this, $e);
            }

            $this->observers = [];

            return;
        }

        if ($suspended) {
            // Catch exceptions produced by the kernel API or the yielded
            // awaitable and return them to the current generator ...
            try {
                if ($produced instanceof Generator) {
                    $this->stack[$this->depth++] = $this->current;
                    $this->current = $produced;
                    goto next;
                } elseif ($produced instanceof CoroutineProvider) {
                    $this->stack[$this->depth++] = $this->current;
                    $this->current = $produced->coroutine();
                    goto next;
                } elseif ($produced instanceof ApiCall) {
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
                    $this->api->dispatch(
                        $this,
                        $this->current->key(),
                        $produced
                    );
                }

                // resume/throw() has already been called ...
                if ($this->action) {
                    goto action;
                }
            } catch (Throwable $e) {
                $this->action = 'throw';
                $this->value = $e;
                goto action;
            }

            $this->state = StrandState::SUSPENDED;
        } elseif ($this->depth) {
            $current = &$this->stack[--$this->depth];
            $this->current = $current;
            $current = null;

            $this->action = 'send';
            $this->value = $produced;
            goto action;
        } else {
            $this->current = null;
            $this->state = StrandState::SUCCESS;

            foreach ($this->observers as $observer) {
                $observer->success($this, $produced);
            }
        }
    }

    /**
     * @var int The strand Id.
     */
    private $id;

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
     * @var int The call stack depth (not including the top element).
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
     * @var int The current state of the strand.
     */
    private $state = StrandState::READY;

    /**
     * @var array<StrandObserver> The objects observing this strand.
     */
    private $observers = [];

    /**
     * @var string|null The next action to perform on the current coroutine ('send' or 'throw').
     */
    private $action;

    /**
     * @var mixed The value or exception to send or throw on the next tick.
     */
    private $value;
}

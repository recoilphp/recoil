<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Closure;
use Generator;
use InvalidArgumentException;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Exception\PrimaryListenerRemovedException;
use Recoil\Kernel\Exception\StrandListenerException;
use Throwable;

/**
 * The standard strand implementation.
 */
trait StrandTrait
{
    /**
     * @param Kernel $kernel     The kernel on which the strand is executing.
     * @param Api    $api        The kernel API used to handle yielded values.
     * @param int    $id         The strand ID.
     * @param mixed  $entryPoint The strand's entry-point coroutine.
     */
    public function __construct(
        Kernel $kernel,
        Api $api,
        int $id,
        $entryPoint
    ) {
        $this->kernel = $kernel;
        $this->primaryListener = $kernel;
        $this->api = $api;
        $this->id = $id;

        if ($entryPoint instanceof Generator) {
            $this->current = $entryPoint;
        } elseif ($entryPoint instanceof CoroutineProvider) {
            $this->current = $entryPoint->coroutine();
        } elseif (
            $entryPoint instanceof Closure || // perf
            \is_callable($entryPoint)
        ) {
            $this->current = $entryPoint();

            if (!$this->current instanceof Generator) {
                throw new InvalidArgumentException(
                    'Callable must return a generator.'
                );
            }
        } else {
            $this->current = (static function () use ($entryPoint) {
                return yield $entryPoint;
            })();
        }
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
     * Start the strand.
     */
    public function start()
    {
        // This method intentionally minimises function calls for performance
        // reasons at the expense of readability. It's nasty. Be gentle.

        // Strand was terminated before it was even started ...
        if ($this->state === StrandState::EXIT_TERMINATED) {
            return;
        }

        assert(
            $this->state === StrandState::READY ||
            (
                $this->state === StrandState::SUSPENDED &&
                $this->action !== null
            ),
            'strand must be READY or SUSPENDED to start'
        );

        assert(
            $this->current instanceof Generator,
            'strand cannot run with empty call stack / invalid generator'
        );

        $this->state = StrandState::RUNNING;

        // Execute the next "tick" of the current coroutine ...
        try {
            // Send the current coroutine data via a 'send' or 'throw'. These
            // actions are configured by the resume and throw methods ...
            if ($this->action) {
                action:
                assert(
                    $this->action === 'send' ||
                    $this->action === 'throw',
                    'the "action" label requires an action and value'
                );

                $this->current->{$this->action}($this->value);
                $this->action = $this->value = null;
            }

            // If the generator is valid (has futher iterations to perform) then
            // it has been suspended with a yield, otherwise it has returned ...
            next:
            $suspended = $this->current->valid();

            if ($suspended) {
                $produced = $this->current->current();
            } else {
                $produced = $this->current->getReturn();
            }

        // This block catches exceptions produced by the coroutine itself ...
        } catch (Throwable $e) {

            // If there is a calling coroutine on the call-stack the exception
            // is propagated up the stack ...
            if ($this->depth) {

                // "fast" functionless stack-pop ...
                $current = &$this->stack[--$this->depth];
                $this->current = $current;
                $current = null;

                $this->action = 'throw';
                $this->value = $e;
                goto action;
            }

            // Otherwise the strand exits with a failure ...
            $this->current = null;
            $this->state = StrandState::EXIT_FAIL;
            $this->result = $e;

            // Notify all listeners ...
            try {
                $this->primaryListener->throw($e, $this);

                foreach ($this->listeners as $listener) {
                    $listener->throw($e, $this);
                }

            // Notify the kernel if any of the listeners fail ...
            } catch (Throwable $e) {
                $this->kernel->throw(
                    new StrandListenerException($this, $e),
                    $this
                );
            } finally {
                $this->primaryListener = null;
                $this->listeners = [];
            }

            // This strand has now exited ...
            return;
        }

        // Handle the key and value produced by a suspended coroutine ...
        if ($suspended) {
            try {
                if ($produced instanceof Generator) {
                    // "fast" functionless stack-push ...
                    $this->stack[$this->depth++] = $this->current;
                    $this->current = $produced;
                    goto next; // enter the new coroutine
                } elseif ($produced instanceof CoroutineProvider) {
                    // The coroutine is extracted from the providor before the
                    // stack push is begun in case coroutine() throws ...
                    $produced = $produced->coroutine();

                    // "fast" functionless stack-push ...
                    $this->stack[$this->depth++] = $this->current;
                    $this->current = $produced;
                    goto next; // enter the new coroutine
                } elseif ($produced instanceof ApiCall) {
                    $result = $this->api->{$produced->name}(
                        $this,
                        ...$produced->arguments
                    );

                    if ($result instanceof Generator) {
                        // "fast" functionless stack-push ...
                        $this->stack[$this->depth++] = $this->current;
                        $this->current = $result;
                        goto next; // enter the new coroutine
                    }
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

                // If action is already set, it means that send() or throw()
                // has already been called above, jump directly to the next
                // action ...
                if ($this->action) {
                    goto action;
                }

            // This block catches exceptions that occur inside the kernel API,
            // awaitables, coroutine providers, etc. The caller is resumed with
            // the exception ...
            } catch (Throwable $e) {
                $this->action = 'throw';
                $this->value = $e;
                goto action;
            }

            // The strand was terminated cleanly ...
            if ($this->state === StrandState::EXIT_TERMINATED) {
                return;
            }

            // No goto sent us back to the "action" label, this means the strand
            // itself (not just the coroutine) is suspended ...
            $this->state = StrandState::SUSPENDED;

        // The current coroutine has exited cleanly (not just suspended), and
        // there is a calling coroutine on the stack, so the current coroutine
        // is popped and the parent is resumed with its returh value ...
        } elseif ($this->depth) {
            // "fast" functionless stack-pop ...
            $current = &$this->stack[--$this->depth];
            $this->current = $current;
            $current = null;

            // Define next action ...
            $this->action = 'send';
            $this->value = $produced;
            goto action;

        // The current coroutine has exited cleanly and there are no coroutines
        // above it on the call-stack ...
        } else {
            $this->current = null;
            $this->state = StrandState::EXIT_SUCCESS;
            $this->result = $produced;

            // Notify all listeners ...
            try {
                $this->primaryListener->send($produced, $this);

                foreach ($this->listeners as $listener) {
                    $listener->send($produced, $this);
                }

            // Notify the kernel if any of the listeners fail ...
            } catch (Throwable $e) {
                $this->kernel->throw(
                    new StrandListenerException($this, $e),
                    $this
                );
            } finally {
                $this->primaryListener = null;
                $this->listeners = [];
            }
        }
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
        if ($this->state === StrandState::EXIT_TERMINATED) {
            return;
        }

        assert(
            $this->state < StrandState::EXIT_SUCCESS,
            'strand can not be terminated after it has exited'
        );

        $this->current = null;
        $this->state = StrandState::EXIT_TERMINATED;
        $this->result = new TerminatedException($this);
        $this->stack = [];

        if ($this->terminator) {
            ($this->terminator)($this);
        }

        // Notify all listeners ...
        try {
            $this->primaryListener->throw($this->result, $this);

            foreach ($this->listeners as $listener) {
                $listener->throw($this->result, $this);
            }

        // Notify the kernel if any of the listeners fail ...
        } catch (Throwable $e) {
            $this->kernel->throw(
                new StrandListenerException($this, $e),
                $this
            );
        } finally {
            $this->primaryListener = null;
            $this->listeners = [];
        }
    }

    /**
     * Resume execution of a suspended strand.
     *
     * @param mixed       $value  The value to send to the coroutine on the the top of the call stack.
     * @param Strand|null $strand The strand that resumed this one, if any.
     */
    public function send($value = null, Strand $strand = null)
    {
        // Ignore resumes after termination, not all asynchronous operations
        // will have meaningful cancel operations and some may attempt to resume
        // the strand after it has been terminated.
        if ($this->state === StrandState::EXIT_TERMINATED) {
            return;
        }

        assert(
            $this->state === StrandState::RUNNING ||
            $this->state === StrandState::SUSPENDED,
            'strand must be suspended to resume'
        );

        $this->terminator = null;
        $this->action = 'send';
        $this->value = $value;

        if ($this->state !== StrandState::RUNNING) {
            $this->start();
        }
    }

    /**
     * Resume execution of a suspended strand with an error.
     *
     * @param Throwable   $exception The exception to send to the coroutine on the top of the call stack.
     * @param Strand|null $strand    The strand that resumed this one, if any.
     */
    public function throw(Throwable $exception, Strand $strand = null)
    {
        // Ignore resumes after termination, not all asynchronous operations
        // will have meaningful cancel operations and some may attempt to resume
        // the strand after it has been terminated.
        if ($this->state === StrandState::EXIT_TERMINATED) {
            return;
        }

        assert(
            $this->state === StrandState::RUNNING ||
            $this->state === StrandState::SUSPENDED,
            'strand must be suspended to resume'
        );

        $this->terminator = null;
        $this->action = 'throw';
        $this->value = $exception;

        if ($this->state !== StrandState::RUNNING) {
            $this->start();
        }
    }

    /**
     * Check if the strand has exited.
     */
    public function hasExited() : bool
    {
        return $this->state >= StrandState::EXIT_SUCCESS;
    }

    /**
     * Set the primary listener.
     *
     * If the current primary listener is not the kernel, it is notified with
     * a {@see PrimaryListenerRemovedException}.
     *
     * @return null
     */
    public function setPrimaryListener(Listener $listener)
    {
        if ($this->state < StrandState::EXIT_SUCCESS) {
            $previous = $this->primaryListener;
            $this->primaryListener = $listener;

            if ($previous !== $this->kernel) {
                $previous->throw(
                    new PrimaryListenerRemovedException($previous, $this),
                    $this
                );
            }
        } elseif ($this->state === StrandState::EXIT_SUCCESS) {
            $listener->send($this->result, $this);
        } else {
            $listener->throw($this->result, $this);
        }
    }

    /**
     * Set the primary listener to the kernel.
     *
     * The current primary listener not notified.
     */
    public function clearPrimaryListener()
    {
        $this->primaryListener = $this->kernel;
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
        assert(
            !$fn || !$this->terminator,
            'only a single terminator can be set'
        );

        assert(
            $this->state !== StrandState::EXIT_TERMINATED,
            'terminator can not be attached to terminated strand'
        );

        $this->terminator = $fn;
    }

    /**
     * The Strand interface extends AwaitableProvider, but this particular
     * implementation can provide await functionality directly.
     *
     * Implementations must favour await() over awaitable() when both are
     * available to avoid a pointless performance hit.
     */
    public function awaitable() : Awaitable
    {
        return $this;
    }

    /**
     * Attach a listener to this object.
     *
     * @param Listener $listener The object to resume when the work is complete.
     * @param Api      $api      The API implementation for the current kernel.
     *
     * @return null
     */
    public function await(Listener $listener, Api $api)
    {
        if ($this->state < StrandState::EXIT_SUCCESS) {
            $this->listeners[] = $listener;
        } elseif ($this->state === StrandState::EXIT_SUCCESS) {
            $listener->send($this->result, $this);
        } else {
            $listener->throw($this->result, $this);
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
     * @var int The strand Id.
     */
    private $id;

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
     * @var Listener|null The strand's primary listener.
     */
    private $primaryListener;

    /**
     * @var array<Listener> Objects to notify when this strand exits.
     */
    private $listeners = [];

    /**
     * @var callable|null A callable invoked when the strand is terminated.
     */
    private $terminator;

    /**
     * @var int The current state of the strand.
     */
    private $state = StrandState::READY;

    /**
     * @var mixed The result of the strand's entry point coroutine, or the
     *            exception it threw.
     */
    private $result;

    /**
     * @var string|null The next action to perform on the current coroutine ('send' or 'throw').
     */
    private $action;

    /**
     * @var mixed The value or exception to send or throw on the next tick.
     */
    private $value;
}

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Closure;
use Generator;
use InvalidArgumentException;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Exception\PrimaryListenerRemovedException;
use Recoil\Kernel\Exception\StrandListenerException;
use SplObjectStorage;
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
    public function kernel() : Kernel
    {
        return $this->kernel;
    }

    /**
     * Start the strand.
     */
    public function start()
    {
        // This method intentionally minimizes function calls for performance
        // reasons at the expense of readability. It's nasty. Be gentle.

        // Strand was terminated before it was even started ...
        if ($this->state === StrandState::EXIT_TERMINATED) {
            return;
        }

        assert(
            $this->state === StrandState::READY ||
            (
                $this->state === StrandState::SUSPENDED_INACTIVE &&
                $this->action !== null
            ),
            'strand must be READY or SUSPENDED_INACTIVE to start'
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
                resume_generator:
                assert(
                    $this->state === StrandState::RUNNING,
                    'strand cannot not perform action unless it is running'
                );

                assert(
                    $this->action === 'send' || $this->action === 'throw',
                    'the "resume_generator" label requires an action of "send" or "throw"'
                );

                assert(
                    $this->action === 'send' || $this->value instanceof Throwable,
                    'the "resume_generator" label requires a throwable when the action is "throw"'
                );

                $this->current->{$this->action}($this->value);
                $this->action = $this->value = null;
            }

            start_generator:
            assert(
                $this->state === StrandState::RUNNING,
                'strand cannot not perform action unless it is running'
            );

            // If the generator is valid it has futher iterations to perform,
            // therefore it has yielded, rather than returned ...
            if ($this->current->valid()) {
                $produced = $this->current->current();
                $this->state = StrandState::SUSPENDED_ACTIVE;

                try {
                    // Another generated was yielded, push it onto the call
                    // stack and execute it ...
                    if ($produced instanceof Generator) {
                        // "fast" functionless stack-push ...
                        $this->stack[$this->depth++] = $this->current;
                        $this->current = $produced;
                        $this->state = StrandState::RUNNING;
                        goto start_generator;

                    // A coroutine provided was yielded. Extract the coroutine
                    // then push it onto the call stack and execute it ...
                    } elseif ($produced instanceof CoroutineProvider) {
                        // The coroutine is extracted from the provider before the
                        // stack push is begun in case coroutine() throws ...
                        $produced = $produced->coroutine();

                        // "fast" functionless stack-push ...
                        $this->stack[$this->depth++] = $this->current;
                        $this->current = $produced;
                        $this->state = StrandState::RUNNING;
                        goto start_generator;

                    // An API call was made through the Recoil static facade ...
                    } elseif ($produced instanceof ApiCall) {
                        $produced = $this->api->{$produced->name}(
                            $this,
                            ...$produced->arguments
                        );

                        // The API call is implemented as a generator coroutine,
                        // push it onto the call stack and execute it ...
                        if ($produced instanceof Generator) {
                            // "fast" functionless stack-push ...
                            $this->stack[$this->depth++] = $this->current;
                            $this->current = $produced;
                            $this->state = StrandState::RUNNING;
                            goto start_generator;
                        }

                    // A generic awaitable object was yielded ...
                    } elseif ($produced instanceof Awaitable) {
                        $produced->await($this, $this->api);

                    // An awaitable provider was yeilded ...
                    } elseif ($produced instanceof AwaitableProvider) {
                        $produced->awaitable()->await($this, $this->api);

                    // Some unidentified value was yielded, allow the API to
                    // dispatch the operation as it sees fit ...
                    } else {
                        $this->api->dispatch(
                            $this,
                            $this->current->key(),
                            $produced
                        );
                    }

                // An exception occurred as a result of the yielded value. This
                // exception is not propagated up the call stack, but rather
                // sent back to the current coroutine (i.e., the one that yielded
                // the value) ...
                } catch (Throwable $e) {
                    $this->action = 'throw';
                    $this->value = $e;
                    $this->state = StrandState::RUNNING;
                    goto resume_generator;
                }

                // The strand has been set back to the READY state. This means
                // that send() or throw() was called while handling the yielded
                // value. Resume the current coroutine immediately ...
                if ($this->state === StrandState::READY) {
                    $this->state = StrandState::RUNNING;
                    goto resume_generator;

                // Otherwise, if the strand was not terminated while handling
                // the yielded value, it is now fully suspended ...
                } elseif ($this->state !== StrandState::EXIT_TERMINATED) {
                    $this->state = StrandState::SUSPENDED_INACTIVE;
                }

                // There is nothing left to do until send() or throw() is called
                // in the future ...
                return;
            }

            // The generator has completed iteration and returned a value ...
            $this->action = 'send';
            $this->value = $this->current->getReturn();

            // Prepare the state in case the stack is empty ...
            $this->state = StrandState::EXIT_SUCCESS;

        // An exception was thrown during the execution of the generator ...
        } catch (Throwable $e) {
            $this->action = 'throw';
            $this->value = $e;

            // Prepare the state in case the stack is empty ...
            $this->state = StrandState::EXIT_FAIL;
        }

        // The current coroutine has ended, either by returning or throwing. If
        // there is a function above it on the call stack, we pop the current
        // coroutine from the stack and resume the parent ...
        if ($this->depth) {
            // "fast" functionless stack-pop ...
            $current = &$this->stack[--$this->depth];
            $this->current = $current;
            $current = null;

            $this->state = StrandState::RUNNING;
            goto resume_generator;
        }

        // Otherwise the call stack is empty, the strand has exited ...
        return $this->finalize();
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
        if ($this->state >= StrandState::EXIT_SUCCESS) {
            return;
        }

        $this->stack = [];
        $this->action = 'throw';
        $this->value = new TerminatedException($this);
        $this->state = StrandState::EXIT_TERMINATED;

        if ($this->terminator) {
            ($this->terminator)($this);
        }

        $this->finalize();
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
            $this->state === StrandState::SUSPENDED_ACTIVE ||
            $this->state === StrandState::SUSPENDED_INACTIVE,
            'strand must be suspended to resume'
        );

        $this->terminator = null;
        $this->action = 'send';
        $this->value = $value;

        if ($this->state === StrandState::SUSPENDED_INACTIVE) {
            $this->start();
        } else {
            $this->state = StrandState::READY;
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
            $this->state === StrandState::SUSPENDED_ACTIVE ||
            $this->state === StrandState::SUSPENDED_INACTIVE,
            'strand must be suspended to resume'
        );

        $this->terminator = null;
        $this->action = 'throw';
        $this->value = $exception;

        if ($this->state === StrandState::SUSPENDED_INACTIVE) {
            $this->start();
        } else {
            $this->state = StrandState::READY;
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
            $listener->send($this->value, $this);
        } else {
            $listener->throw($this->value, $this);
        }
    }

    /**
     * Set the primary listener to the kernel.
     *
     * The current primary listener is not notified.
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
            $this->state === StrandState::READY ||
            $this->state === StrandState::SUSPENDED_ACTIVE ||
            $this->state === StrandState::SUSPENDED_INACTIVE,
            'strand must be suspended to set a terminator'
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
            $listener->send($this->value, $this);
        } else {
            $listener->throw($this->value, $this);
        }
    }

    /**
     * Create a uni-directional link to another strand.
     *
     * If this strand exits, any linked strands are terminated.
     *
     * @return null
     */
    public function link(Strand $strand)
    {
        if ($this->linkedStrands === null) {
            $this->linkedStrands = new SplObjectStorage();
        }

        $this->linkedStrands->attach($strand);
    }

    /**
     * Break a previously created uni-directional link to another strand.
     *
     * @return null
     */
    public function unlink(Strand $strand)
    {
        if ($this->linkedStrands !== null) {
            $this->linkedStrands->detach($strand);
        }
    }

    /**
     * Finalize the strand by notifying any listeners of the exit and
     * terminating any linked strands.
     */
    private function finalize()
    {
        $this->current = null;

        try {
            $this->primaryListener->{$this->action}($this->value, $this);

            foreach ($this->listeners as $listener) {
                $listener->{$this->action}($this->value, $this);
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

        if ($this->linkedStrands !== null) {
            try {
                foreach ($this->linkedStrands as $strand) {
                    $strand->unlink($this);
                    $strand->terminate();
                }
            } finally {
                $this->linkedStrands = null;
            }
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
     * @var SplObjectStorage<Strand>|null Strands to terminate when this strand
     *                                    is terminated.
     */
    private $linkedStrands;

    /**
     * @var int The current state of the strand.
     */
    private $state = StrandState::READY;

    /**
     * @var string|null The next action to perform on the current coroutine ('send' or 'throw').
     */
    private $action;

    /**
     * @var mixed The value or exception to send or throw on the next tick or
     *            the result of the strand's entry point coroutine if the strand
     *            has exited.
     */
    private $value;
}

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Closure;
use Generator;
use InvalidArgumentException;
use Recoil\Kernel\Exception\StrandFailedException;
use Recoil\Kernel\Exception\StrandObserverFailedException;
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
     *
     * @param mixed $coroutine The strand's entry-point.
     */
    public function start($coroutine)
    {
        // Strand was terminated before it was even started ...
        if ($this->state === StrandState::EXIT_TERMINATED) {
            return;
        }

        assert(
            $this->state === StrandState::READY,
            'strand can not be started multiple times'
        );

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

        $this->run();
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
        $this->stack = [];

        if ($this->terminator) {
            ($this->terminator)($this);
        }

        if ($this->observer) {
            try {
                $this->observer->terminated($this);
            } catch (Throwable $e) {
                $this->kernel->triggerException(new StrandObserverFailedException(
                    $this,
                    $this->observer,
                    $e
                ));
            } finally {
                $this->observer = null;
            }
        }

        if (!empty($this->waitingStrands)) {
            $this->resumeWaitingStrands();
        }
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
            $this->run();
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
            $this->run();
        }
    }

    /**
     * Set the strand observer.
     *
     * @return null
     */
    public function setObserver(StrandObserver $observer = null)
    {
        assert(
            $this->state < StrandState::EXIT_SUCCESS,
            'observer can not be changed after strand has exited'
        );

        $this->observer = $observer;
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
     * @param Strand $strand The strand to resume on completion.
     * @param Api    $api    The kernel API.
     */
    public function await(Strand $strand, Api $api)
    {
        if ($this->state < StrandState::EXIT_SUCCESS) {
            $this->waitingStrands[] = $strand;
        } else {
            $strand->resume();
        }
    }

    private function run()
    {
        // This method intentionally minimises function calls for performance
        // reasons at the expense of readability. It's nasty. Be gentle.

        assert(
            $this->state !== StrandState::RUNNING,
            __METHOD__ . '() is not re-entrant'
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

            // If there is an observer, it 'intercepts' the error from the
            // kernel ...
            if ($this->observer) {
                try {
                    $this->observer->failure($this, $e);
                } catch (Throwable $e) {
                    $this->kernel->triggerException(new StrandObserverFailedException(
                        $this,
                        $this->observer,
                        $e
                    ));
                } finally {
                    $this->observer = null;
                }

            // The kernel is notified of the failure if there is no observer ...
            } else {
                $this->kernel->triggerException(new StrandFailedException(
                    $this,
                    $e
                ));
            }

            if (!empty($this->waitingStrands)) {
                $this->resumeWaitingStrands();
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

                // If action is already set, it means that resume() or throw()
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
        // above it on the call-stack. Notify the observer of the success ...
        } else {
            $this->current = null;
            $this->state = StrandState::EXIT_SUCCESS;

            if ($this->observer) {
                try {
                    $this->observer->success($this, $produced);
                } catch (Throwable $e) {
                    $this->kernel->triggerException(new StrandObserverFailedException(
                        $this,
                        $this->observer,
                        $e
                    ));
                } finally {
                    $this->observer = null;
                }
            }

            if (!empty($this->waitingStrands)) {
                $this->resumeWaitingStrands();
            }
        }
    }

    private function resumeWaitingStrands()
    {
        try {
            foreach ($this->waitingStrands as $strand) {
                $strand->resume();
            }
        } finally {
            $this->waitingStrands = [];
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
     * @var StrandObserver|null The strand observer.
     */
    private $observer;

    /**
     * @var callable|null A callable invoked when the strand is terminated.
     */
    private $terminator;

    /**
     * @var array<Strand> Strands to resume when this strand exits.
     */
    private $waitingStrands = [];

    /**
     * @var int The current state of the strand.
     */
    private $state = StrandState::READY;

    /**
     * @var string|null The next action to perform on the current coroutine ('send' or 'throw').
     */
    private $action;

    /**
     * @var mixed The value or exception to send or throw on the next tick.
     */
    private $value;
}

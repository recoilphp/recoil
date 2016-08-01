<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Exception\KernelStoppedException;
use Recoil\Kernel\Exception\StrandException;
use Recoil\Kernel\Kernel;
use Recoil\Kernel\Listener;
use Recoil\Kernel\Strand;
use Throwable;

/**
 * A Recoil coroutine kernel based on a ReactPHP event loop.
 */
final class ReactKernel implements Kernel
{
    /**
     * Execute a coroutine on a new kernel.
     *
     * This is a convenience method for:
     *
     *     $kernel = new Kernel($eventLoop);
     *     $kernel->waitFor($coroutine);
     *
     * @param mixed              $coroutine The strand's entry-point.
     * @param LoopInterface|null $eventLoop The event loop to use (null = default).
     *
     * @return mixed               The return value of the coroutine.
     * @throws Throwable           The exception produced by the coroutine, if any.
     * @throws TerminatedException The strand has been terminated.
     * @throws StrandException     A strand failure was not handled by the exception handler.
     */
    public static function start($coroutine, LoopInterface $eventLoop = null)
    {
        $kernel = new self($eventLoop);
        $strand = $kernel->execute($coroutine);

        return $kernel->waitForStrand($strand);
    }

    /**
     * @param LoopInterface|null $eventLoop The event loop.
     * @param Api|null           $api       The kernel API.
     */
    public function __construct(LoopInterface $eventLoop = null, Api $api = null)
    {
        $this->eventLoop = $eventLoop ?: Factory::create();
        $this->api = $api ?: new ReactApi($this->eventLoop);
    }

    /**
     * Execute a coroutine on a new strand.
     *
     * Execution is deferred until control returns to the kernel. This allows
     * the caller to manipulate the returned {@see Strand} object before
     * execution begins.
     *
     * @param mixed $coroutine The strand's entry-point.
     */
    public function execute($coroutine) : Strand
    {
        $strand = new ReactStrand($this, $this->api, $this->nextId++, $coroutine);

        $this->eventLoop->futureTick(
            function () use ($strand) {
                $strand->start();
            }
        );

        return $strand;
    }

    /**
     * Run the kernel until all strands exit or the kernel is stopped.
     *
     * Calls to wait(), {@see Kernel::waitForStrand()} and {@see Kernel::waitFor()}
     * may be nested. This can be useful within synchronous code to block
     * execution until a particular asynchronous operation is complete. Care
     * must be taken to avoid deadlocks.
     *
     * @throws StrandException        One or more strands produced unhandled exceptions.
     * @throws KernelStoppedException The kernel has been stopped and the outer-most wait() call has not yet returned.
     */
    public function wait()
    {
        if ($this->state >= self::STATE_UNWINDING_STOP) {
            throw new KernelStoppedException();
        }

        try {
            $this->state = self::STATE_RUNNING;
            ++$this->waitDepth;
            $this->eventLoop->run();

            if (!empty($this->unhandledExceptions)) {
                throw new StrandException($this->unhandledExceptions);
            }
        } finally {
            if (--$this->waitDepth === 0) {
                $this->state = self::STATE_STOPPED;
                $this->unhandledExceptions = [];
            }
        }
    }

    /**
     * Run the kernel until a specific strand exits or the kernel is stopped.
     *
     * If the strand fails, its exception is NOT passed to the kernel's
     * exception handler, instead it is re-thrown by this method.
     *
     * Calls to {@see Kernel::wait()}, waitForStrand() and {@see Kernel::waitFor()}
     * may be nested. This can be useful within synchronous code to block
     * execution until a particular asynchronous operation is complete. Care
     * must be taken to avoid deadlocks.
     *
     * @param Strand $strand The strand to wait for.
     *
     * @return mixed                  The strand result, on success.
     * @throws Throwable              The exception thrown by the strand, if failed.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException Execution was stopped with {@see Kernel::stop()} before the strand exited.
     * @throws KernelStoppedException The kernel has been stopped and the outer-most wait() call has not yet returned.
     * @throws StrandException        One or more other strands produced unhandled exceptions.
     */
    public function waitForStrand(Strand $strand)
    {
        assert($strand->kernel() === $this, 'kernel can only wait for its own strands');

        if ($this->isUnwinding) {
            throw new KernelStoppedException();
        }

        $listener = new class() implements Listener
        {
            public $eventLoop;
            public $isPending = false;
            public $value;
            public $exception;

            public function send($value = null, Strand $strand = null)
            {
                $this->isPending = false;
                $this->value = $value;

                if ($this->eventLoop) {
                    $this->eventLoop->stop();
                }
            }

            public function throw(Throwable $exception, Strand $strand = null)
            {
                $this->isPending = false;
                $this->exception = $exception;

                if ($this->eventLoop) {
                    $this->eventLoop->stop();
                }
            }
        };

        $strand->setPrimaryListener($listener);

        if ($listener->isPending) {
            $listener->eventLoop = $this->eventLoop;

            try {
                ++$this->waitDepth;

                do {
                    $this->eventLoop->run();

                    if (!empty($this->unhandledExceptions)) {
                        throw new StrandException($this->unhandledExceptions);
                    } elseif ($isUnwinding) {
                        throw new KernelStoppedException();
                    }
                } while ($listener->isPending);
            } finally {
                if (--$this->waitDepth === 0) {
                    $this->isUnwinding = false;
                    $this->unhandledExceptions = [];
                }
            }
        }

        if ($listener->exception) {
            throw $listener->exception;
        }

        return $listener->value;
    }

    /**
     * Run the kernel until the given coroutine returns or the kernel is stopped.
     *
     * This is a convenience method equivalent to:
     *
     *      $strand = $kernel->execute($coroutine);
     *      $kernel->waitForStrand($strand);
     *
     * If the strand fails, its exception is NOT passed to the kernel's
     * exception handler, instead it is re-thrown by this method.
     *
     * Calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()} and waitFor()
     * may be nested. This can be useful within synchronous code to block
     * execution until a particular asynchronous operation is complete. Care
     * must be taken to avoid deadlocks.
     *
     * @param mixed $coroutine The coroutine to execute.
     *
     * @return mixed                  The return value of the coroutine.
     * @throws Throwable              The exception produced by the coroutine, if any.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException Execution was stopped with {@see Kernel::stop()}.
     * @throws KernelStoppedException The kernel has been stopped and the outer-most wait() call has not yet returned.
     * @throws StrandException        One or more other strands produced unhandled exceptions.
     */
    public function waitFor($coroutine)
    {
        return $this->waitForStrand(
            $this->execute($coroutine)
        );
    }

    /**
     * Stop the kernel.
     *
     * The kernel can not be restarted until the outer-most call to {@see Kernel::wait()},
     * {@see Kernel::waitForStrand()} and {@see Kernel::waitFor()} has returned.
     */
    public function stop()
    {
        $this->isUnwinding = true;
        $this->eventLoop->stop();
    }

    /**
     * Set a user-defined exception handler function.
     *
     * The exception handler function is invoked when a strand exits with an
     * unhandled failure. That is, whenever an exception propagates to the top
     * of the strand's call-stack and the strand does not already have a
     * mechanism in place to deal with the exception.
     *
     * The exception handler function must have the following signature:
     *
     *      function (Strand $strand, Throwable $exception)
     *
     * The first parameter is the strand that produced the exception, the second
     * is the exception itself.
     *
     * The handler may re-throw the exception to indicate that it cannot be
     * handled. In this case (or when there is no exception handler) a {@see StrandException}
     * is thrown by all nested calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()}
     * or {@see Kernel::waitFor()}.
     *
     * The kernel can not be restarted until the outer-most call to {@see Kernel::wait()},
     * {@see Kernel::waitForStrand()} and {@see Kernel::waitFor()} has thrown.
     *
     * @param callable|null $fn The exception handler (null = remove).
     */
    public function setExceptionHandler(callable $fn = null)
    {
        $this->exceptionHandler = $fn;
    }

    /**
     * Send the result of a successful operation.
     *
     * @param mixed       $value  The operation result.
     * @param Strand|null $strand The strand that produced this result upon exit, if any.
     */
    public function send($value = null, Strand $strand = null)
    {
        assert(
            $strand !== null && $strand->kernel() === $this,
            'kernel can only handle notifications from its own strands'
        );
    }

    /**
     * Send the result of an unsuccessful operation.
     *
     * @param Throwable   $exception The operation result.
     * @param Strand|null $strand    The strand that produced this exception upon exit, if any.
     */
    public function throw(Throwable $exception, Strand $strand = null)
    {
        assert(
            $strand !== null && $strand->kernel() === $this,
            'kernel can only handle notifications from its own strands'
        );

        // Don't treat termination of this strand as an error ...
        if (
            $exception instanceof TerminatedException &&
            $strand === $exception->strand()
        ) {
            return;
        }

        if ($this->exceptionHandler) {
            try {
                return ($this->exceptionHandler)($exception);
            } catch (Throwable $e) {
                $exception = $e;
            }
        }

        $this->unhandledExceptions[$strand->id()] = $exception;
        $this->eventLoop->stop();
    }

    private function throwUnhandledExceptions()
    {
        if (empty($this->unhandledExceptions)) {
            return;
        }

        $exception = new StrandException($this->unhandledExceptions);

        // Clear the exceptions if this is the outer-most wait call ...
        if ($this->waitDepth === 0) {
            $this->unhandledExceptions = [];
        }

        throw $exception;
    }

    const STATE_STOPPED = 0;
    const STATE_RUNNING = 1;
    const STATE_UNWINDING_STOP = 2;
    const STATE_UNWINDING_THROW = 3;

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;

    /**
     * @var Api The kernel API.
     */
    private $api;

    /**
     * @var int The next strand ID.
     */
    private $nextId = 1;

    /**
     * @var int
     */
    private $state = self::STATE_STOPPED;

    /**
     * @var int The number of nested calls to any of the wait() methods.
     */
    private $waitDepth = 0;

    /**
     * @var callable|null The exception handler.
     */
    private $exceptionHandler;

    /**
     * @var array<int, Throwable> A map of strand ID to the unhandled exception they
     *                            produced.
     */
    private $unhandledExceptions = [];
}

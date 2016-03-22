<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Kernel;
use Recoil\Kernel\KernelTrait;
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
     * This method blocks until the kernel has nothing left to do, or is
     * interrupted.
     *
     * @param mixed              $coroutine The strand's entry-point.
     * @param LoopInterface|null $eventLoop The event loop to use (null = default).
     *
     * @return mixed               The return value of the coroutine.
     * @throws Throwable           The exception produced by the coroutine, if any.
     * @throws Throwable           The exception used to interrupt the kernel.
     * @throws TerminatedException The strand has been terminated.
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
        $strand = new ReactStrand($this->nextId++, $this, $this->api);

        $this->eventLoop->futureTick(
            function () use ($strand, $coroutine) {
                $strand->start($coroutine);
            }
        );

        return $strand;
    }

    /**
     * Run the kernel until all strands exit or the kernel is stopped.
     *
     * Calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()} and
     * {@see Kernel::waitFor()} may be nested. This can be useful within
     * synchronous code to block execution until a particular asynchronous
     * operation is complete. Care must be taken to avoid deadlocks.
     *
     * @see Kernel::waitForStrand() to wait for a specific strand.
     * @see Kernel::waitFor() to wait for a specific awaitable.
     * @see Kernel::stop() to stop the kernel.
     * @see Kernel::setExceptionHandler() to control how strand failures are handled.
     *
     * @return bool            False if the kernel was stopped with {@see Kernel::stop()}; otherwise, true.
     * @throws StrandException A strand or strand observer has failed when thre is no exception handler.
     */
    public function wait()
    {
        ++$this->depth;
        try {
            $this->eventLoop->run();
        } finally {
            --$this->depth;
        }

        if ($this->depth !== $this->stopAtDepth) {
            $this->eventLoop->run();
        }

        if ($this->interruptException) {
            $exception = $this->interruptException;
            $this->interruptException = null;

            throw $exception;
        }
    }

    /**
     * Stop the kernel.
     *
     * The outer-most call to {@see Kernel::wait()}, {@see Kernel::waitForStrand()}
     * or {@see Kernel::waitFor()} is stopped.
     *
     * {@see Kernel::wait()} returns false when the kernel is stopped, the other
     * variants throw a {@see KernelStoppedException}.
     *
     * @return null
     */
    public function stop()
    {
        $this->stopAtDepth = $this->depth - 1;
        $this->eventLoop->stop();
    }

    /**
     * Set the exception handler.
     *
     * The exception handler is invoked whenever a strand fails. That is, when
     * an exception is allowed to propagate to the top of the strand's
     * call-stack. Or, when a strand observer throws an exception.
     *
     * The exception handler function must accept a single parameter of type
     * {@see StrandException}.
     *
     * By default, or if the exception handler is explicitly set to NULL, the
     * exception will instead be thrown by the outer-most call to {@see Kernel::wait()},
     * {@see Kernel::waitForStrand()} or {@see Kernel::waitFor()}, after which
     * the kernel may not be restarted.
     *
     * @param callable|null $fn The error handler (null = remove).
     *
     * @return null
     */
    public function setExceptionHandler(callable $fn = null)
    {
    }

    use KernelTrait;

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;

    /**
     * @var Api The kernel API.
     */
    private $api;

    /**
     * @var int The current nesting depth of calls to->run().
     *
     * React's event loop do not directly support nested calls to run(). That is,
     * calling stop() at any level will stop iteration in all calls to run().
     *
     * Therefore, we need to call run() a second time if stop() has not been
     * called enough times to bail from the current depth.
     */
    private $depth = 0;

    /**
     * @var int The depth at which calls to run() should NOT be repeated.
     */
    private $stopAtDepth = 0;

    /**
     * @var int The next strand ID.
     */
    private $nextId = 1;

    /**
     * @var Throwable|null The exception passed to interrupt(), if any.
     */
    private $interruptException;
}

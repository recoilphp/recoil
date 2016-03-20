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
     * Start a new strand of execution.
     *
     * The implementation must delay execution of the strand until the next
     * 'tick' of the kernel to allow the user to inspect the strand object
     * before execution begins.
     *
     * @param mixed $coroutine The strand's entry-point.
     *
     * @return Strand
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
     * Run the kernel and wait for all strands to exit.
     *
     * Calls to wait() and waitForStrand() can be nested, which can be used in
     * synchronous code to block until a particular operation is complete.
     * However, care must be taken not to introduce deadlocks.
     *
     * @see Kernel::waitFor()
     * @see Kernel::interrupt()
     *
     * @return null
     * @throws Throwable The exception passed to {@see Kernel::interrupt()}.
     */
    public function wait()
    {
        $this->eventLoop->run();

        if ($this->interruptException) {
            $exception = $this->interruptException;
            $this->interruptException = null;

            throw $exception;
        }
    }

    /**
     * Interrupt the kernel.
     *
     * Execution of all strands is paused and the given exception is thrown by
     * the current call to {@see Kernel::wait()}. wait() can be called again to
     * resume execution of remaining strands.
     */
    public function interrupt(Throwable $exception)
    {
        $this->interruptException = $exception;
        $this->eventLoop->stop();
    }

    /**
     * Stop the kernel.
     */
    public function stop()
    {
        $this->eventLoop->stop();
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
     * @var int The next strand ID.
     */
    private $nextId = 1;

    /**
     * @var Throwable|null The exception passed to interrupt(), if any.
     */
    private $interruptException;
}

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Recoil\Exception\TerminatedException;
use RuntimeException;
use Throwable;

trait KernelTrait
{
    /**
     * Run the kernel until a specific strand exits or the kernel is stopped.
     *
     * Calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()} and
     * {@see Kernel::waitFor()} may be nested. This can be useful within
     * synchronous code to block execution until a particular asynchronous
     * operation is complete. Care must be taken to avoid deadlocks.
     *
     * @see Kernel::wait() to wait for all strands.
     * @see Kernel::waitFor() to wait for a specific awaitable.
     * @see Kernel::stop() to stop the kernel.
     *
     * @param Strand $strand The strand to wait for.
     *
     * @return mixed                  The strand result, on success.
     * @throws Throwable              The exception thrown by the strand, if failed.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException Execution was stopped with {@see Kernel::stop()}.
     */
    public function waitForStrand(Strand $strand)
    {
        $observer = new class implements StrandObserver
        {
            public $value;
            public $exception;
            public $exited = false;

            public function success(Strand $strand, $value)
            {
                $strand->kernel()->stop();
                $this->exited = true;
                $this->value = $value;
            }

            public function failure(Strand $strand, Throwable $exception)
            {
                $strand->kernel()->stop();
                $this->exited = true;
                $this->exception = $exception;
            }

            public function terminated(Strand $strand)
            {
                $strand->kernel()->stop();
                $this->exited = true;
                $this->exception = new TerminatedException($strand);
            }
        };

        $strand->setObserver($observer);

        $this->wait();

        if ($observer->exception) {
            throw $observer->exception;
        } elseif ($observer->exited) {
            return $observer->value;
        }

        throw new RuntimeException('The strand never exited.');
    }

    /**
     * Run the kernel until the given coroutine returns or the kernel is stopped.
     *
     * This is a convenience method equivalent to:
     *
     *      $strand = $kernel->execute($coroutine);
     *      $kernel->waitForStrand($strand);
     *
     * Calls to {@see Kernel::wait()}, {@see Kernel::waitForStrand()} and
     * {@see Kernel::waitFor()} may be nested. This can be useful within
     * synchronous code to block execution until a particular asynchronous
     * operation is complete. Care must be taken to avoid deadlocks.
     *
     * @see Kernel::execute() to start a new strand.
     * @see Kernel::waitForStrand() to wait for a specific strand.
     * @see Kernel::wait() to wait for all strands.
     * @see Kernel::stop() to stop the kernel.
     *
     * @param mixed $coroutine The coroutine to execute.
     *
     * @return mixed                  The return value of the coroutine.
     * @throws Throwable              The exception produced by the coroutine, if any.
     * @throws TerminatedException    The strand has been terminated.
     * @throws KernelStoppedException Execution was stopped with {@see Kernel::stop()}.
     */
    public function waitFor($coroutine)
    {
        return $this->waitForStrand(
            $this->execute($coroutine)
        );
    }

    /**
     * Start a new strand of execution.
     *
     * The implementation must delay execution of the strand until the next
     * 'tick' of the kernel to allow the user to inspect the strand object
     * before execution begins.
     *
     * @param mixed $coroutine The strand's entry-point.
     */
    public abstract function execute($coroutine) : Strand;

    /**
     * Run the kernel and wait for all strands to exit.
     *
     * Calls to wait() and waitForStrand() can be nested, which can be used in
     * synchronous code to block until a particular operation is complete.
     * However, care must be taken not to introduce deadlocks.
     *
     * @see Kernel::waitForStrand()
     * @see Kernel::interrupt()
     *
     * @return null
     * @throws Throwable The exception passed to {@see Kernel::interrupt()}.
     */
    public abstract function wait();
}

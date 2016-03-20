<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Kernel;
use Recoil\Kernel\KernelTrait;
use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandObserver;
use RuntimeException;
use Throwable;

trait KernelTrait
{
    /**
     * Run the kernel and wait for a specific strand to exit.
     *
     * Calls to wait() and waitForStrand() can be nested, which can be used in
     * synchronous code to block until a particular operation is complete.
     * However, care must be taken not to introduce deadlocks.
     *
     * @see Kernel::wait()
     * @see Kernel::interrupt()
     *
     * @param Strand $strand The strand to wait for.
     *
     * @return mixed The return value of the strand's entry-point coroutine.
     * @throws Throwable The exception produced by the strand, if any.
     * @throws Throwable The exception used to interrupt the kernel.
     * @throws TerminatedException The strand has been terminated.
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
                $this->exited = true;
                $this->value = $value;
            }

            public function failure(Strand $strand, Throwable $exception)
            {
                $this->exited = true;
                $this->exception = $exception;
            }

            public function terminated(Strand $strand)
            {
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

        throw new RuntimeException('The entry-point coroutine never returned.');
    }

    /**
     * Run the kernel and wait for a specific coroutine to exit.
     *
     * This is a convenience method equivalent to:
     *
     *      $strand = $kernel->execute($coroutine);
     *      $kernel->waitForStrand($strand);
     *
     * @see Kernel::execute()
     * @see Kernel::waitForStrand()
     *
     * @param mixed              $coroutine The strand's entry-point.
     *
     * @return mixed The return value of the coroutine.
     * @throws Throwable The exception produced by the coroutine, if any.
     * @throws Throwable The exception used to interrupt the kernel.
     * @throws TerminatedException The strand has been terminated.
     */
    public function waitFor($coroutine)
    {
        $this->waitForStrand(
            $this->execute($coroutine)
        );
    }
}

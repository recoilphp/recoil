<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use Recoil\Kernel\Kernel;
use Recoil\Kernel\Listener;
use Recoil\Kernel\Strand;
use Throwable;

/**
 * @access private
 *
 * This listener is used to implement ReactKernel::start()
 */
final class StopListener implements Listener
{
    /**
     * @var bool
     */
    public $isDone = false;

    /**
     * @param Kernel $kernel The kernel to stop when the listener is notified.
     */
    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Send the result of a successful operation.
     *
     * @param mixed       $value  The operation result.
     * @param Strand|null $strand The strand that produced this result upon exit, if any.
     */
    public function send($value = null, Strand $strand = null)
    {
        $this->isDone = true;
        $this->value = $value;
        $this->kernel->stop();
    }

    /**
     * Send the result of an unsuccessful operation.
     *
     * @param Throwable   $exception The operation result.
     * @param Strand|null $strand    The strand that produced this exception upon exit, if any.
     */
    public function throw(Throwable $exception, Strand $strand = null)
    {
        $this->isDone = true;
        $this->exception = $exception;
        $this->kernel->stop();
    }

    /**
     * Get the strand result.
     *
     * @return mixed The strand result.
     *
     * @throws Throwable The strand exception, if any.
     */
    public function get()
    {
        if ($this->exception) {
            throw $this->exception;
        }

        return $this->value;
    }

    /**
     * @var KernelInterface
     */
    public $kernel;

    /**
     * @var mixed The strand result.
     */
    private $value;

    /**
     * @var Throwable|null The strand exception, if any.
     */
    private $exception;
}

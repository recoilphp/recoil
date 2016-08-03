<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use Recoil\Kernel\Listener;
use Recoil\Kernel\Strand;
use Throwable;

/**
 * @access private
 *
 * This listener is used to implement ReactKernel::adoptSync()
 */
final class AdoptSyncListener implements Listener
{
    /**
     * @var LoopInterface|null The event loop to stop when the strand completes.
     */
    public $eventLoop;

    /**
     * @var bool True if the listener has been notified of a result.
     */
    public $isDone = false;

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

        if ($this->eventLoop) {
            $this->eventLoop->stop();
        }
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

        if ($this->eventLoop) {
            $this->eventLoop->stop();
        }
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
     * @var mixed The strand result.
     */
    private $value;

    /**
     * @var Throwable|null The strand exception, if any.
     */
    private $exception;
}

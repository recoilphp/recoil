<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Recoil\Exception\TerminatedException;
use Recoil\Exception\TimeoutException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Awaitable;
use Recoil\Kernel\Resumable;
use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandObserver;
use Throwable;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 *
 * React timer based implementation of Api::timeout().
 */
final class StrandTimeout implements Awaitable, StrandObserver
{
    public function __construct(
        LoopInterface $eventLoop,
        float $timeout,
        Strand $substrand
    ) {
        $this->eventLoop = $eventLoop;
        $this->timeout = $timeout;
        $this->substrand = $substrand;
    }

    /**
     * Perform the work.
     *
     * @param Resumable $resumable The object to resume when the work is complete.
     * @param Api       $api       The API implementation for the current kernel.
     */
    public function await(Resumable $resumable, Api $api)
    {
        $this->timer = $this->eventLoop->addTimer(
            $this->timeout,
            [$this, 'timeout']
        );

        $this->resumable = $resumable;
        $this->resumable->setTerminator([$this, 'cancel']);

        $this->substrand->setObserver($this);
    }

    /**
     * A strand exited successfully.
     *
     * @param Strand $strand The strand.
     * @param mixed  $value  The result of the strand's entry point coroutine.
     */
    public function success(Strand $strand, $value)
    {
        assert($this->substrand === $strand, 'unknown strand');

        $this->substrand = null;
        $this->timer->cancel();
        $this->resumable->resume($value);
    }

    /**
     * A strand exited with a failure due to an uncaught exception.
     *
     * @param Strand    $strand    The strand.
     * @param Throwable $exception The exception.
     */
    public function failure(Strand $strand, Throwable $exception)
    {
        assert($this->substrand === $strand, 'unknown strand');

        $this->substrand = null;
        $this->timer->cancel();
        $this->resumable->throw($exception);
    }

    /**
     * A strand exited because it was terminated.
     *
     * @param Strand $strand The strand.
     */
    public function terminated(Strand $strand)
    {
        assert($this->substrand === $strand, 'unknown strand');

        $this->substrand = null;
        $this->timer->cancel();
        $this->resumable->throw(new TerminatedException($strand));
    }

    /**
     * Terminate all remaining strands.
     */
    public function cancel()
    {
        if ($this->substrand) {
            $this->timer->cancel();
            $this->substrand->setObserver(null);
            $this->substrand->terminate();
        }
    }

    /**
     * Terminate all remaining strands, and resume the calling strand with a
     * {@see TimeoutException}.
     */
    public function timeout()
    {
        if ($this->substrand) {
            $this->substrand->setObserver(null);
            $this->substrand->terminate();

            $this->resumable->throw(new TimeoutException($this->timeout));
        }
    }

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;

    /**
     * @var float The timeout, in seconds.
     */
    private $timeout;

    /**
     * @var TimerInterface|null The timeout timer.
     */
    private $timer;

    /**
     * @var Resumable|null The object to resume upon completion.
     */
    private $resumable;

    /**
     * @var Strand|null The strand to wait for.
     */
    private $substrand;
}

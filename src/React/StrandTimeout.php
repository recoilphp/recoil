<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Recoil\Exception\TerminatedException;
use Recoil\Exception\TimeoutException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Awaitable;
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
     * @param Strand $strand The strand to resume on completion.
     * @param Api    $api    The kernel API.
     */
    public function await(Strand $strand, Api $api)
    {
        $this->timer = $this->eventLoop->addTimer(
            $this->timeout,
            [$this, 'timeout']
        );

        $this->strand = $strand;
        $this->strand->setTerminator([$this, 'cancel']);

        $this->substrand->setObserver($this);
    }

    /**
     * A strand completed successfully.
     *
     * @param Strand $strand The strand.
     * @param mixed  $value  The result of the strand's entry point coroutine.
     */
    public function success(Strand $strand, $value)
    {
        assert($this->substrand === $strand, 'unknown strand');

        $this->substrand = null;
        $this->timer->cancel();
        $this->strand->resume($value);
    }

    /**
     * A strand failed due to an uncaught exception.
     *
     * @param Strand    $strand    The strand.
     * @param Throwable $exception The exception.
     */
    public function failure(Strand $strand, Throwable $exception)
    {
        assert($this->substrand === $strand, 'unknown strand');

        $this->substrand = null;
        $this->timer->cancel();
        $this->strand->throw($exception);
    }

    /**
     * A strand was terminated.
     *
     * @param Strand $strand The strand.
     */
    public function terminated(Strand $strand)
    {
        assert($this->substrand === $strand, 'unknown strand');

        $this->substrand = null;
        $this->timer->cancel();
        $this->strand->throw(new TerminatedException($strand));
    }

    /**
     * Terminate all pending strands.
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
     * Terminate all pending strands.
     */
    public function timeout()
    {
        if ($this->substrand) {
            $this->substrand->setObserver(null);
            $this->substrand->terminate();

            $this->strand->throw(new TimeoutException($this->timeout));
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
     * @var Strand|null The strand to resume.
     */
    private $strand;

    /**
     * @var Strand|null The strand to wait for.
     */
    private $substrand;
}

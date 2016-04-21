<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Recoil\Exception\TimeoutException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Awaitable;
use Recoil\Kernel\Listener;
use Recoil\Kernel\Strand;
use Throwable;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 *
 * React timer based implementation of Api::timeout().
 */
final class StrandTimeout implements Awaitable, Listener
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
     * Attach a listener to this object.
     *
     * @param Listener $listener The object to resume when the work is complete.
     * @param Api      $api      The API implementation for the current kernel.
     *
     * @return null
     */
    public function await(Listener $listener, Api $api)
    {
        $this->timer = $this->eventLoop->addTimer(
            $this->timeout,
            [$this, 'timeout']
        );

        if ($listener instanceof Strand) {
            $listener->setTerminator([$this, 'cancel']);
        }

        $this->listener = $listener;

        $this->substrand->setPrimaryListener($this);
    }

    /**
     * Send the result of a successful operation.
     *
     * @param mixed       $value  The operation result.
     * @param Strand|null $strand The strand that that is the source of the result, if any.
     */
    public function send($value = null, Strand $strand = null)
    {
        assert($this->substrand === $strand, 'unknown strand');

        $this->substrand = null;
        $this->timer->cancel();
        $this->listener->send($value);
    }

    /**
     * Send the result of an un successful operation.
     *
     * @param Throwable   $exception The operation result.
     * @param Strand|null $strand    The strand that that is the source of the result, if any.
     */
    public function throw(Throwable $exception, Strand $strand = null)
    {
        assert($this->substrand === $strand, 'unknown strand');

        $this->substrand = null;
        $this->timer->cancel();
        $this->listener->throw($exception);
    }

    /**
     * Terminate all remaining strands.
     */
    public function cancel()
    {
        if ($this->substrand) {
            $this->timer->cancel();
            $this->substrand->clearPrimaryListener();
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
            $this->substrand->clearPrimaryListener();
            $this->substrand->terminate();

            $this->listener->throw(new TimeoutException($this->timeout));
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
     * @var Listener|null The object to notify upon completion.
     */
    private $listener;

    /**
     * @var Strand|null The strand to wait for.
     */
    private $substrand;
}

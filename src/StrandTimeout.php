<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

use Recoil\Awaitable;
use Recoil\Exception\TimeoutException;
use Recoil\Kernel\Api;
use Recoil\Kernel\SystemStrand;
use Recoil\Listener;
use Recoil\Strand;
use Throwable;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 *
 * An implementation of Api::timeout() based on the reference kernel's event
 * queue.
 */
final class StrandTimeout implements Awaitable, Listener
{
    /**
     * @var EventQueue   The event queue used to schedule the timeout event.
     * @var float        $timeout   The timeout, in seconds.
     * @var SystemStrand $substrand The strand to wait for.
     */
    public function __construct(
        EventQueue $events,
        float $timeout,
        SystemStrand $substrand
    ) {
        $this->events = $events;
        $this->timeout = $timeout;
        $this->substrand = $substrand;
    }

    /**
     * Attach a listener to this object.
     *
     * @param Listener $listener The object to resume when the work is complete.
     */
    public function await(Listener $listener)
    {
        $this->cancel = $this->events->schedule(
            $this->timeout,
            function () {
                if ($this->substrand) {
                    $this->substrand->clearPrimaryListener();
                    $this->substrand->terminate();

                    $this->listener->throw(TimeoutException::create($this->timeout));
                }
            }
        );

        if ($listener instanceof Strand) {
            $listener->setTerminator(function () {
                if ($this->substrand) {
                    ($this->cancel)();
                    $this->substrand->clearPrimaryListener();
                    $this->substrand->terminate();
                }
            });
        }

        $this->listener = $listener;

        $this->substrand->setPrimaryListener($this);
    }

    /**
     * Send the result of a successful operation.
     *
     * @param mixed       $value  The operation result.
     * @param Strand|null $strand The strand that produced this result upon exit, if any.
     */
    public function send($value = null, Strand $strand = null)
    {
        assert($this->substrand === $strand, 'unknown strand');

        $this->substrand = null;
        ($this->cancel)();
        $this->listener->send($value);
    }

    /**
     * Send the result of an unsuccessful operation.
     *
     * @param Throwable   $exception The operation result.
     * @param Strand|null $strand    The strand that produced this exception upon exit, if any.
     */
    public function throw(Throwable $exception, Strand $strand = null)
    {
        assert($this->substrand === $strand, 'unknown strand');

        $this->substrand = null;
        ($this->cancel)();
        $this->listener->throw($exception);
    }

    /**
     * @var EventQueue The event queue used to schedule the timeout event.
     */
    private $events;

    /**
     * @var callable|null The function to call to cancel the timeout event.
     */
    private $cancel;

    /**
     * @var float The timeout, in seconds.
     */
    private $timeout;

    /**
     * @var Listener|null The object to notify upon completion.
     */
    private $listener;

    /**
     * @var Strand|null The strand to wait for.
     */
    private $substrand;
}

<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

use SplPriorityQueue;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 * @final
 *
 * EventQueue is used to schedule events for execution after a delay.
 */
class EventQueue
{
    public function __construct()
    {
        $this->queue = new SplPriorityQueue();
    }

    /**
     * Schedule an event for execution.
     *
     * Any events scheduled from within an event action function are
     * guaranteed not to be executed during the current tick.
     *
     * @param float    $delay The delay before execution, in seconds.
     * @param callable $fn    The action to perform after the delay.
     *
     * @return callable A function used to cancel the event.
     */
    public function schedule(float $delay, callable $fn): callable
    {
        $time = \microtime(true) + $delay;
        $event = new Event($time, $fn);

        $this->queue->insert($event, -$time);
        ++$this->queueSize;
        ++$this->pendingEvents;

        if ($this->queueSize === 1 || $time < $this->nextTime) {
            $this->nextTime = $time;
        }

        return function () use ($event) {
            if ($event->fn !== null) {
                $event->fn = null;
                --$this->pendingEvents;
            }
        };
    }

    /**
     * Execute any pending events and remove them from the queue.
     *
     * @return int|null The number if microseconds until the next event (null = none).
     */
    public function tick()
    {
        $time = \microtime(true);

        while (
            $this->pendingEvents > 0 &&
            $this->nextTime <= $time
        ) {
            $event = $this->queue->extract();
            --$this->queueSize;

            try {
                if ($event->fn) {
                    $fn = $event->fn;
                    $event->fn = null;
                    --$this->pendingEvents;
                    $fn();
                }
            } finally {
                if ($this->pendingEvents !== 0) {
                    $nextEvent = $this->queue->top();
                    $this->nextTime = $nextEvent->time;
                }
            }
        }

        if ($this->pendingEvents === 0) {
            if ($this->queueSize !== 0) {
                $this->queue = new SplPriorityQueue();
                $this->queueSize = 0;
            }

            return null;
        }

        $delta = $this->nextTime - \microtime(true);

        if ($delta <= 0) {
            return 0;
        }

        return (int) ($delta * 1000000);
    }

    /**
     * @var SplPriorityQueue<Event>
     */
    private $queue;

    /**
     * @var int The number of events in the queue.
     */
    private $queueSize = 0;

    /**
     * @var int The number of active (uncancelled) events in the queue.
     */
    private $pendingEvents = 0;

    /**
     * @var float The execution time of the next event in the queue.
     */
    private $nextTime = 0;
}

<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

use SplPriorityQueue;

/**
 * @access private
 */
final class EventQueue
{
    public function __construct()
    {
        $this->queue = new SplPriorityQueue();
    }

    public function schedule(float $delay, callable $fn)
    {
        $time = \microtime(true) + $delay;
        $event = new Event($time, $fn);

        $this->queue->insert($event, -$time);
        ++$this->queueSize;
        ++$this->pendingEvents;

        if ($time < $this->nextTime) {
            $this->nextTime = $time;
        }

        return function () use ($event) {
            if ($event->fn !== null) {
                $event->fn = null;
                --$this->pendingEvents;
            }
        };
    }

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

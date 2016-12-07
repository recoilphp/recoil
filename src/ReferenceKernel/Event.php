<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 *
 * Event represents a time-based event, used to implement sleep
 * and time based API operations.
 */
final class Event
{
    /**
     * @var float The time at which the event is scheduled, in seconds.
     */
    public $time;

    /**
     * @var callable|null The action to perform when the event fires (null = cancelled).
     */
    public $fn;

    /**
     * @param float    $time The time at which the event is scheduled, in seconds.
     * @param callable $fn   The action to perform when the event fires.
     */
    public function __construct(float $time, callable $fn)
    {
        $this->time = $time;
        $this->fn = $fn;
    }
}

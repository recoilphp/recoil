<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

/**
 * @access private
 */
final class Event
{
    public $time;
    public $fn;

    public function __construct(float $time, callable $fn)
    {
        $this->time = $time;
        $this->fn = $fn;
    }
}

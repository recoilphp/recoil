<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Trace;

final class TraceYield implements Trace
{
    public function __construct(string $file, int $line, $value = null)
    {
        $this->value = $value;
    }

    public function unwrap()
    {
        return $this->value;
    }

    public $value;
}

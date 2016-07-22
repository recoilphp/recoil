<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Trace;

use IteratorAggregate;

final class TraceYieldFrom implements Trace, IteratorAggregate
{
    public function __construct(string $file, int $line, $value)
    {
        $this->value = $value;
    }

    public function unwrap()
    {
        return $this->value;
    }

    public function getIterator()
    {
        foreach ($this->value as $key => $value) {
            yield $key => $value;
        }
    }

    private $value;
}

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel\Debug;

final class Trace
{
    public $value;
    public $function;
    public $file;
    public $line;

    public function __construct($value, string $function, string $file, int $line)
    {
        $this->value = $value;
        $this->function = $function;
        $this->file = $file;
        $this->line = $line;
    }
}

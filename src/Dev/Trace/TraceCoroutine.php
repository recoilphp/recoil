<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Trace;

use IteratorAggregate;

/**
 * Provides information about a coroutine when first executed.
 */
final class TraceCoroutine extends Trace
{
    /**
     * @var string The name of the coroutine function.
     */
    public $function;

    /**
     * @param string $file     The file containing the coroutine.
     * @param int    $line     The line number of coroutine definition.
     * @param string $function The name of the coroutine function.
     */
    public function __construct(string $file, int $line, string $function)
    {
        $this->file = $file;
        $this->line = $line;
        $this->function = $function;
    }
}

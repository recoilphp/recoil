<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Trace;

abstract class Trace
{
    /**
     * @var string The filename.
     */
    public $file;

    /**
     * @var int The line number.
     */
    public $line;

    /**
     * @param string $file     The file containing the coroutine.
     * @param int    $line     The line number of coroutine definition.
     * @param string $function The name of the coroutine function.
     */
    public static function coroutine(string $file, int $line, string $function) : TraceCoroutine
    {
        return new TraceCoroutine($file, $line, $function);
    }

    /**
     * @param string $file  The file containing the coroutine that yielded.
     * @param int    $line  The line number of the yielded value.
     * @param mixed  $value The yielded value.
     */
    public static function yield(string $file, int $line, $value = null) : TraceYield
    {
        return new TraceYield($file, $line, $value);
    }
}

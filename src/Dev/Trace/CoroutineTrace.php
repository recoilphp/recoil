<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Trace;

/**
 * Provides information about a coroutine when first executed.
 */
final class CoroutineTrace implements Trace
{
    /**
     * @var string The filename.
     */
    public $file;

    /**
     * @var string The name of the coroutine function.
     */
    public $function;

    /**
     * @var array
     */
    public $arguments;

    /**
     * @param string $file      The file containing the coroutine that yielded.
     * @param string $function  The name of the coroutine function.
     * @param array  $arguments The arguments to the coroutine.
     */
    public function __construct(
        string $file,
        string $function,
        array $arguments
    ) {
        $this->file = $file;
        $this->function = $function;
        $this->arguments = $arguments;
    }
}

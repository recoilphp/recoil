<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Trace;

use Recoil\Dev\Instrumentation\InstrumentationDirective;
use Recoil\Kernel\Strand;

/**
 * Provides information about a coroutine when first executed.
 */
final class CoroutineTrace implements InstrumentationDirective
{
    /**
     * @var string The filename.
     */
    public $file;

    /**
     * @var int|null The line number of the most recently executed yield.
     */
    public $yieldLine;

    /**
     * @var string The name of the coroutine function.
     */
    public $function;

    /**
     * @var array The arguments passed to the coroutine.
     */
    public $arguments;

    /**
     * @param string $file      The file containing the coroutine that yielded.
     * @param int    $line      The line number of the first statement.
     * @param string $function  The name of the coroutine function.
     * @param array  $arguments The arguments to the coroutine.
     */
    public function __construct(
        string $file,
        int $line,
        string $function,
        array $arguments
    ) {
        $this->file = $file;
        $this->yieldLine = $line;
        $this->function = $function;
        $this->arguments = $arguments;
    }

    /**
     * Execute the directive.
     *
     * This method is invoked when this value is yielded from a strand.
     *
     * @param Strand $strand The strand that yielded this value.
     * @param object $frame  A context object on which information may be stored
     *                       for the current stack frame.
     */
    public function execute(Strand $strand, $frame)
    {
        $frame->trace = $this;
        $strand->send(true, $strand);
    }
}

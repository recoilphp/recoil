<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Trace;

use Recoil\Dev\Instrumentation\InstrumentationDirective;
use Recoil\Kernel\Strand;

/**
 * Provides information about a yield statement inside a coroutine.
 */
final class YieldTrace implements InstrumentationDirective
{
    /**
     * @param int $line The line number of the yield statement.
     */
    public function __construct(int $line)
    {
        $this->line = $line;
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
        assert(isset($frame->trace), 'no coroutine trace present for this frame');

        $frame->trace->yieldLine = $this->line;
        $strand->send(true, $strand);
    }

    /**
     * @var int The line number.
     */
    public $line;
}

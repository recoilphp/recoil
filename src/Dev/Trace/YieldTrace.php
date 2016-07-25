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
     * @param int   $line  The line number of the yielded value.
     * @param mixed $value The yielded value.
     */
    public function __construct(int $line, $value = null)
    {
        $this->line = $line;
        $this->value = $value;
    }

    /**
     * Execute the directive.
     *
     * This method is invoked when this value is yielded from a strand.
     *
     * @param Strand $strand The strand that yielded this value.
     * @param mixed  $key    The yielded key.
     * @param object $frame  A context object on which information may be stored
     *                       for the current stack frame.
     *
     * @return tuple<bool, mixed> A 2-tuple. If the first value is true, the
     *                     strand must treat the second element as though
     *                     it were the yielded value.
     */
    public function execute(Strand $strand, $key, $frame) : array
    {
        assert(isset($frame->trace));
        $frame->trace->yieldLineNumber = $this->line;

        return [true, $this->value];
    }

    /**
     * @var int The line number.
     */
    public $line;

    /**
     * @var mixed The original yielded value.
     */
    private $value;
}

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use Recoil\Kernel\Strand;

/**
 * A value yielded from a coroutine to perform instrumentation.
 */
interface InstrumentationDirective
{
    /**
     * Execute the directive.
     *
     * This method is invoked when this value is yielded from a strand.
     *
     * @param Strand $strand The strand that yielded this value.
     * @param object $frame  A context object on which information may be stored
     *                       for the current stack frame.
     */
    public function execute(Strand $strand, $frame);
}

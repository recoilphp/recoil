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
     * @param mixed  $key    The yielded key.
     * @param object $frame  A context object on which information may be stored
     *                       for the current stack frame.
     *
     * @return tuple<bool, mixed> A 2-tuple. If the first value is true, the
     *                     strand must treat the second element as though
     *                     it were the yielded value.
     */
    public function execute(Strand $strand, $key, $frame) : array;
}

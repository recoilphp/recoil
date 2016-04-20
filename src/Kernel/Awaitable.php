<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

/**
 * An awaitable unit of work that can notify listeners upon completion.
 */
interface Awaitable
{
    /**
     * Attach a listener to this object.
     *
     * @param Listener $listener The object to resume when the work is complete.
     * @param Api      $api      The API implementation for the current kernel.
     *
     * @return null
     */
    public function await(Listener $listener, Api $api);
}

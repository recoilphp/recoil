<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

/**
 * An object that can be yielded from a coroutine to perform work.
 */
interface Awaitable
{
    /**
     * Perform the work.
     *
     * @param Resumable $resumable The object to resume when the work is complete.
     * @param Api       $api       The API implementation for the current kernel.
     *
     * @return null
     */
    public function await(Resumable $resumable, Api $api);
}

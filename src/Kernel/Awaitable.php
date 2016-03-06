<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

/**
 * An object that can be yielded from a coroutine to perform work.
 */
interface Awaitable
{
    /**
     * Perform the work.
     *
     * @param Strand $strand The strand to resume on completion.
     * @param Api    $api    The kernel API.
     */
    public function await(Strand $strand, Api $api);
}

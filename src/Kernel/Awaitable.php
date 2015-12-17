<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

/**
 * An object that can be yielded from a coroutine to perform work.
 */
interface Awaitable
{
    /**
     * Perform the work and resume strand upon completion.
     *
     * @param Strand      $strand The executing strand.
     * @param Api         $api    The kernel API.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function await(Strand $strand, Api $api);
}

<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

/**
 * An object that can perform work and resume a suspendable when complete.
 */
interface Awaitable
{
    /**
     * Perform the work and resume the caller upon completion.
     *
     * @param Strand      $strand The executing strand.
     * @param Suspendable $caller The waiting object.
     * @param Api         $api    The kernel API.
     */
    public function await(Strand $strand, Suspendable $caller, Api $api);
}

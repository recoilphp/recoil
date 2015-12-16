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
     * @param Suspendable $caller The waiting object.
     * @param Api         $api    The kernel API.
     */
    public function await(Suspendable $caller, Api $api);
}

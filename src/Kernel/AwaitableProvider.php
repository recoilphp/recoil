<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

/**
 * An object that produces awaitables.
 */
interface AwaitableProvider
{
    public function awaitable() : Awaitable;
}

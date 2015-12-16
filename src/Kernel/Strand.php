<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

interface Strand extends Awaitable, Suspendable
{
    /**
     * Terminate this strand.
     */
    public function terminate();

    /**
     * Capture the result of the strand, supressing the default error handling
     * behaviour.
     *
     * The exact behavior of this method is defined by the particular kernel
     * that the strand is executing on. For example, the implementation might
     * return a promise that is settled with the result of the strand, or simply
     * blackhole the result.
     *
     * @return mixed
     */
    public function capture();
}

<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

interface Kernel
{
    /**
     * Start a new strand of execution.
     *
     * The implementation must delay execution of the strand until the next
     * 'tick' of the kernel to allow the user to inspect the strand object
     * before execution begins.
     *
     * @param mixed $coroutine The strand's entry-point.
     *
     * @return Strand
     */
    public function execute($coroutine) : Strand;
}

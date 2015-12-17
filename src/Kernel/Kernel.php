<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Generator;

interface Kernel
{
    /**
     * Start a new strand of execution.
     *
     * The coroutine can be a generator object, or a generator function.
     *
     * The implementation must delay execution of the strand until the next
     * 'tick' of the kernel to allow the user to inspect the strand object
     * before execution begins.
     *
     * @param Generator|callable $coroutine The coroutine to execute.
     *
     * @return Strand
     */
    public function execute($coroutine) : Strand;
}

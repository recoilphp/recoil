<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

interface Kernel
{
    /**
     * Start a new strand of execution.
     *
     * The task can be any value that is accepted by the API's __dispatch()
     * method.
     *
     * The kernel implementation must delay execution of the strand until the
     * next tick, allowing the caller to use Strand::capture() if necessary.
     *
     * @param mixed $task The task to execute.
     *
     * @return Strand
     */
    public function execute($task) : Strand;
}

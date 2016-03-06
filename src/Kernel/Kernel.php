<?php

namespace Recoil\Kernel;

use React\EventLoop\LoopInterface;
use Recoil\Coroutine\CoroutineAdaptor;
use Recoil\Kernel\Strand\Strand;
use Recoil\Kernel\Strand\StrandFactory;

/**
 * A coroutine kernel.
 */
interface Kernel
{
    /**
     * Execute a coroutine in a new strand of execution.
     *
     * The parameter may be any value that can be adapted into a coroutine by
     * the kernel's coroutine adaptor.
     *
     * @param mixed $coroutine The coroutine to execute.
     *
     * @return Strand The strand on which the coroutine will execute.
     */
    public function execute($coroutine);

    /**
     * Attach an existing strand to this kernel.
     *
     * @param Strand The strand to attach.
     */
    public function attachStrand(Strand $strand);

    /**
     * Detach an existing strand from this kernel.
     *
     * @param Strand The strand to detach.
     */
    public function detachStrand(Strand $strand);

    /**
     * Fetch the object that implements the kernel API.
     *
     * @return KernelApi The kernel's API implementation.
     */
    public function api();

    /**
     * Fetch the object used to adapt values into coroutines.
     *
     * @return CoroutineAdaptor The kernel's coroutine adaptor.
     */
    public function coroutineAdaptor();

    /**
     * Fetch the factory used to create new strands.
     *
     * @return StrandFactory The kernel's strand factory.
     */
    public function strandFactory();

    /**
     * Fetch the React event-loop.
     *
     * @return LoopInterface The React event-loop.
     */
    public function eventLoop();

    /**
     * Terminate all strands and stop execution.
     *
     * The React event-loop can optionally be stopped when all strands have been
     * terminated.
     *
     * @param boolean $stopEventLoop Indicates whether or not the React event-loop should also be stopped.
     */
    public function stop($stopEventLoop = true);
}

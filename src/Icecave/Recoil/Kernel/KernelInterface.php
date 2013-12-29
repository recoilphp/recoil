<?php
namespace Icecave\Recoil\Kernel;

use Icecave\Recoil\Coroutine\CoroutineAdaptorInterface;
use Icecave\Recoil\Kernel\Strand\StrandFactoryInterface;
use Icecave\Recoil\Kernel\Strand\StrandInterface;
use React\EventLoop\LoopInterface;

/**
 * A coroutine kernel.
 */
interface KernelInterface
{
    /**
     * Execute a coroutine in a new strand of execution.
     *
     * The parameter may be any value that can be adapted into a coroutine by
     * the kernel's coroutine adaptor.
     *
     * @param mixed $coroutine The coroutine to execute.
     *
     * @return StrandInterface The strand on which the coroutine will execute.
     */
    public function execute($coroutine);

    /**
     * Attach an existing strand to this kernel.
     *
     * @param StrandInterface The strand to attach.
     */
    public function attachStrand(StrandInterface $strand);

    /**
     * Detach an existing strand from this kernel.
     *
     * @param StrandInterface The strand to detach.
     */
    public function detachStrand(StrandInterface $strand);

    /**
     * Fetch the object that implements the kernel API.
     *
     * @return KernelApiInterface The kernel's API implementation.
     */
    public function api();

    /**
     * Fetch the object used to adapt values into coroutines.
     *
     * @return CoroutineAdaptorInterface The kernel's coroutine adaptor.
     */
    public function coroutineAdaptor();

    /**
     * Fetch the factory used to create new strands.
     *
     * @return StrandFactoryInterface The kernel's strand factory.
     */
    public function strandFactory();

    /**
     * Fetch the ReactPHP event-loop.
     *
     * @return LoopInterface The ReactPHP event-loop.
     */
    public function eventLoop();
}

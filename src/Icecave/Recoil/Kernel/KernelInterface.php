<?php
namespace Icecave\Recoil\Kernel;

use Icecave\Recoil\Coroutine\CoroutineAdaptorInterface;
use React\EventLoop\LoopInterface;

/**
 * A co-routine kernel.
 */
interface KernelInterface
{
    /**
     * Execute a co-routine in a new strand of execution.
     *
     * The parameter may be any value that can be adapted into a co-routine by
     * the kernel's co-routine adaptor.
     *
     * @param mixed $coroutine The co-routine to execute.
     */
    public function execute($coroutine);

    /**
     * Attach an existing strand to this kernel.
     *
     * @param StrandInterface The strand to attach.
     */
    public function attachStrand(StrandInterface $strand);

    /**
     * Fetch the object that implements the kernel's system calls.
     *
     * @return KernelApiInterface The kernel's API implementation.
     */
    public function api();

    /**
     * Fetch the object used to adapt values into co-routines.
     *
     * @return CoroutineAdaptorInterface The kernel's co-routine adaptor.
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

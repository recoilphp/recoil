<?php
namespace Icecave\Recoil\Kernel;

/**
 * A factory for strands.
 */
interface StrandFactoryInterface
{
    /**
     * Create a strand.
     *
     * @param KernelInterface $kernel The kernel on which the strand will execute.
     *
     * @return StrandInterface
     */
    public function createStrand(KernelInterface $kernel);
}

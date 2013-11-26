<?php
namespace Icecave\Recoil\Kernel\Strand;

use Icecave\Recoil\Kernel\KernelInterface;

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

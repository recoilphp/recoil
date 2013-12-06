<?php
namespace Icecave\Recoil\Kernel\Strand;

use Icecave\Recoil\Kernel\KernelInterface;

/**
 * The default strand factory.
 */
class StrandFactory implements StrandFactoryInterface
{
    /**
     * Create a strand.
     *
     * @param KernelInterface The kernel on which the strand will execute.
     *
     * @return StrandInterface
     */
    public function createStrand(KernelInterface $kernel)
    {
        return new Strand($kernel);
    }

    private $resultHandler;
}

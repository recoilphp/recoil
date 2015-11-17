<?php

namespace Recoil\Kernel\Strand;

use Recoil\Kernel\Kernel;

/**
 * The default strand factory.
 */
class StrandFactory implements StrandFactoryInterface
{
    /**
     * Create a strand.
     *
     * @param Kernel The kernel on which the strand will execute.
     *
     * @return StrandInterface
     */
    public function createStrand(Kernel $kernel)
    {
        return new Strand($kernel);
    }
}

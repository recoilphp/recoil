<?php

namespace Recoil\Kernel\Strand;

use Recoil\Kernel\Kernel;

/**
 * A factory for strands.
 */
interface StrandFactory
{
    /**
     * Create a strand.
     *
     * @param Kernel $kernel The kernel on which the strand will execute.
     *
     * @return Strand
     */
    public function createStrand(Kernel $kernel);
}

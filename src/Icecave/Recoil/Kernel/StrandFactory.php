<?php
namespace Icecave\Recoil\Kernel;

class StrandFactory implements StrandFactoryInterface
{
    public function createStrand(KernelInterface $kernel)
    {
        return new Strand($this->nextId++, $kernel);
    }

    private $nextId = 1;
}

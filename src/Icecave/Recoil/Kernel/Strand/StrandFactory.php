<?php
namespace Icecave\Recoil\Kernel\Strand;

use Icecave\Recoil\Kernel\KernelInterface;

/**
 * The default strand factory.
 */
class StrandFactory implements StrandFactoryInterface
{
    /**
     * @param ResultHandlerInterface $resultHandler Default result handler for new strands.
     */
    public function __construct(ResultHandlerInterface $resultHandler = null)
    {
        if (null === $resultHandler) {
            $resultHandler = new DefaultResultHandler;
        }

        $this->resultHandler = $resultHandler;
    }

    /**
     * Create a strand.
     *
     * @param KernelInterface The kernel on which the strand will execute.
     *
     * @return StrandInterface
     */
    public function createStrand(KernelInterface $kernel)
    {
        return new Strand($kernel, $this->resultHandler);
    }

    private $resultHandler;
}

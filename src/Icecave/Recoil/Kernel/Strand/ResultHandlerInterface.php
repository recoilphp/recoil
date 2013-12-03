<?php
namespace Icecave\Recoil\Kernel\Strand;

/**
 * Handles the results produced by strands.
 */
interface ResultHandlerInterface
{
    /**
     * Handle a strand result.
     *
     * @param StrandInterface       $strand The strand that produced the exception.
     * @param StrandResultInterface $result The result produced by the strand.
     */
    public function handleResult(StrandInterface $strand, StrandResultInterface $result);
}

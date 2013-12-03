<?php
namespace Icecave\Recoil\Kernel\Strand;

/**
 * The default strand result handler.
 *
 * Uncaught exceptions are propagated, values are ignored.
 */
class DefaultResultHandler implements ResultHandlerInterface
{
    /**
     * Handle a strand result.
     *
     * @param StrandInterface       $strand The strand that produced the exception.
     * @param StrandResultInterface $result The result produced by the strand.
     */
    public function handleResult(StrandInterface $strand, StrandResultInterface $result)
    {
        if ($exception = $result->getException()) {
            throw $exception;
        }
    }
}

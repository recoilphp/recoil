<?php
namespace Icecave\Recoil\Kernel\ExceptionHandler;

use Exception;
use Icecave\Recoil\Kernel\Strand\StrandInterface;

/**
 * Handles uncaught exceptions produced by strands.
 */
interface ExceptionHandlerInterface
{
    /**
     * Handle an exception.
     *
     * @param StrandInterface $strand    The strand that produced the exception.
     * @param Exception       $exception The exception that occurred.
     */
    public function handleException(StrandInterface $strand, Exception $exception);
}

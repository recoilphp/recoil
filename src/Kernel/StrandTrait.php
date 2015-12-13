<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Exception;

trait StrandTrait
{
    /**
     * Terminate this strand.
     */
    public function terminate()
    {
        throw new \LogicException('Not implemented.');
    }

    public function awaitable() : Awaitable
    {
        throw new \LogicException('Not implemented.');
    }

    /**
     * Resume execution.
     *
     * @param mixed $result The result.
     */
    public function resume($result = null)
    {
        $this->finalize(null, $result);
    }

    /**
     * Resume execution with an exception.
     *
     * @param Exception $exception The exception.
     */
    public function throw(Exception $exception)
    {
        if (!$this->finalize($exception, null)) {
            // @todo implement kernel-wide error capture
            throw $exception;
        }
    }

    /**
     * A hook used by the implementation to implement it's capture mechanism.
     *
     * @see Strand::capture()
     *
     * @param Exception|null The exception that the strand produced (null = success).
     * @param mixed          The value that the strand produced on success.
     *
     * @return bool True if the result was captured.
     */
    private function finalize(Exception $exception = null, $result = null) : bool
    {
        return false;
    }
}

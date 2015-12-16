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

    /**
     * Perform the work and resume the caller upon completion.
     *
     * @param Strand      $strand The executing strand.
     * @param Suspendable $caller The waiting object.
     * @param Api         $api    The kernel API.
     */
    public function await(Strand $strand, Suspendable $caller, Api $api)
    {
        $this->observers[] = $caller;
    }

    /**
     * Resume execution.
     *
     * @param mixed $result The result.
     */
    public function resume($result = null)
    {
        foreach ($this->observers as $observer) {
            $observer->resume($result);
        }

        $this->finalize(null, $result);
    }

    /**
     * Resume execution with an exception.
     *
     * @param Exception $exception The exception.
     */
    public function throw(Exception $exception)
    {
        foreach ($this->observers as $observer) {
            $observer->throw($exception);
        }

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

    /**
     * @var array<Suspendable>
     */
    private $observers = [];
}

<?php

declare (strict_types = 1);

namespace Recoil\React;

use Exception;
use React\Promise\Deferred;
use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandTrait;

final class ReactStrand implements Strand
{
    /**
     * Capture the result of the strand, supressing the default error handling
     * behaviour.
     *
     * @return ExtendedPromiseInterface A promise that is settled with the strand result.
     */
    public function capture()
    {
        if (null === $this->deferred) {
            $this->deferred = new Deferred([$this, 'terminate']);
        }

        return $this->deferred->promise();
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
    private function finalize(Exception $exception = null, $result) : bool
    {
        if (!$this->deferred) {
            return false;
        } elseif ($exception) {
            $this->deferred->reject($exception);
        } else {
            $this->deferred->resolve($result);
        }

        return true;
    }

    use StrandTrait;

    /**
     * @var Deferred|null Used to resolve the promise returned by capture().
     */
    private $deferred;
}

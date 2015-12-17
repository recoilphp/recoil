<?php

declare (strict_types = 1);

namespace Recoil\React;

use Exception;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromisorInterface;
use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandTrait;
use Throwable;

final class ReactStrand implements
    Strand,
    // EventEmitterInterface,
    PromisorInterface
{
    /**
     * Capture the result of the strand, supressing the default error handling
     * behaviour.
     *
     * @return ExtendedPromiseInterface A promise that is settled with the strand result.
     */
    public function promise()
    {
        if (null === $this->deferred) {
            $this->deferred = new Deferred();
        }

        return $this->deferred->promise();
    }

    /**
     * A hook that can be used by the implementation to perform actions upon
     * completion of the strand.
     */
    private function done(Throwable $exception = null, $result = null)
    {
        if ($this->deferred) {
            if ($exception) {
                $this->deferred->reject($exception);
            } else {
                $this->deferred->resolve($result);
            }
        } elseif ($exception) {
            throw $exception;
        }
    }

    // use EventEmitterTrait;
    use StrandTrait;

    /**
     * @var Deferred|null The deferred that resolves the promise returned by capture().
     */
    private $deferred;
}

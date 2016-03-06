<?php

declare (strict_types = 1);

namespace Recoil\React;

use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\PromisorInterface;
use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandTrait;

final class ReactStrand implements Strand, PromisorInterface
{
    /**
     * Capture the result of the strand, supressing the default error handling
     * behaviour.
     *
     * @return ExtendedPromiseInterface A promise that is settled with the strand result.
     */
    public function promise()
    {
        if (!$this->promise) {
            $deferred = new Deferred();
            $this->attachObserver(new DeferredResolver($deferred));
            $this->promise = $deferred->promise();
        }

        return $this->promise;
    }

    use StrandTrait;

    /**
     * @var ExtendedPromiseInterface|null
     */
    private $promise;
}

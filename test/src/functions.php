<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Kernel;
use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandObserver;
use Throwable;

/**
 * A coroutine-based version of it.
 */
function rit(string $description, callable $test)
{
    it($description, function () use ($test) {
        $this->kernel->execute($test)->attachObserver(
            new class implements StrandObserver
            {
                public function success(Strand $strand, $value)
                {
                }
                public function failure(Strand $strand, Throwable $exception)
                {
                    throw $exception;
                }
                public function terminated(Strand $strand)
                {
                    throw new TerminatedException($strand);
                }
             }
        );

        $this->kernel->wait();
    });
}

function defineFunctionalSpec(string $description, callable $factory)
{
    context("Functional Tests ($description)", function () use ($factory) {
        beforeEach(function () use ($factory) {
            $this->kernel = $factory();
            expect($this->kernel)->to->be->an->instanceof(Kernel::class);
        });

        require __DIR__ . '/functional.api.spec.php';
        require __DIR__ . '/functional.invoke.spec.php';
    });
}

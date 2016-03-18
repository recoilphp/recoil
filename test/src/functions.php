<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Recoil\Kernel\Exception\StrandException;
use Recoil\Kernel\Kernel;
use Recoil\Kernel\Strand;

/**
 * A coroutine-based version of it.
 */
function rit(string $description, callable $test)
{
    it($description, function () use ($test) {
        $this->kernel->execute($test);

        try {
            $this->kernel->wait();
        } catch (StrandException $e) {
            throw $e->getPrevious();
        }
    });
}

function defineFunctionalSpec(string $description, callable $factory)
{
    context("Functional Tests ($description)", function () use ($factory) {
        beforeEach(function () use ($factory) {
            $this->kernel = $factory();
            expect($this->kernel)->to->be->an->instanceof(Kernel::class);
        });

        require __DIR__ . '/functional.kernel.spec.php';
        require __DIR__ . '/functional.api.spec.php';
        require __DIR__ . '/functional.strand.spec.php';
    });
}

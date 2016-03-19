<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Recoil\Kernel\Exception\StrandException;
use Recoil\Kernel\Kernel;

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

function importFunctionalTests(string $description, callable $factory)
{
    context("Functional Tests ($description)", function () use ($factory) {
        beforeEach(function () use ($factory) {
            $this->kernel = $factory();
            expect($this->kernel)->to->be->an->instanceof(Kernel::class);
        });

        $walk = null;
        $walk = function ($path) use (&$walk) {
            foreach (scandir($path) as $entry) {
                $fq = $path . '/' . $entry;
                $matches = null;

                if ($entry[0] === '.') {
                    continue;
                } elseif (is_dir($fq)) {
                    context($entry, function () use ($fq, $walk) {
                        $walk($fq);
                    });
                } elseif (preg_match('/^functional.(.+).spec.php$/', $entry, $matches)) {
                    context($matches[1], function () use ($fq) {
                        require $fq;
                    });
                }
            }
        };

        $walk(__DIR__ . '/../suite-functional');
    });
}

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Generator;
use Recoil\Kernel\Exception\StrandException;
use Recoil\Kernel\Kernel;

/**
 * A coroutine-based version of it.
 */
function rit(string $description, callable $test)
{
    it($description, function () use ($test) {
        $result = $test();

        if ($result instanceof Generator) {
            $strand = $this->kernel->execute($test);
        } else {
            $strand = null;
        }

        try {
            $this->kernel->wait();
        } catch (StrandException $e) {
            throw $e->getPrevious();
        }

        if ($strand) {
            expect($strand->hasExited())->to->be->true;
        }
    });
}

function importFunctionalTests(callable $factory)
{
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
}

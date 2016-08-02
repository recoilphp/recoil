<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Kernel\Exception\KernelStoppedException;

it('causes run() to return', function () {
    $this->kernel->execute(function () {
        yield;
        expect(false)->to->be->ok('not stopped');
    });

    $stopCalled = false;
    $this->kernel->execute(function () use (&$stopCalled) {
        $this->kernel->stop();
        $stopCalled = true;

        return;
        yield;
    });

    $this->kernel->run();
    expect($stopCalled)->to->be->true;
});

it('causes adoptSync() to throw', function () {
    $strand = $this->kernel->execute(function () {
        yield;
        expect(false)->to->be->ok('not stopped');
    });
    $this->kernel->execute(function () {
        $this->kernel->stop();

        return;
        yield;
    });

    try {
        $this->kernel->adoptSync($strand);
        expect(false)->to->be->ok('expected exception was not thrown');
    } catch (KernelStoppedException $e) {
        // ok ...
    }
});

it('causes executeSync() to throw', function () {
    $this->kernel->execute(function () {
        $this->kernel->stop();

        return;
        yield;
    });

    try {
        $this->kernel->executeSync(function () {
            yield;
            expect(false)->to->be->ok('not stopped');
        });
        expect(false)->to->be->ok('expected exception was not thrown');
    } catch (KernelStoppedException $e) {
        // ok ...
    }
});

it('causes all nested to run(), executeSync() and adoptSync() to return/throw', function () {
    $this->kernel->execute(function () {
        $strand = yield Recoil::execute(function () {
            try {
                $this->kernel->executeSync(function () {
                    $this->kernel->stop();

                    return;
                    yield;
                });
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (KernelStoppedException $e) {
                // ok ...
            }

            return;
            yield;
        });

        try {
            $this->kernel->adoptSync($strand);
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (KernelStoppedException $e) {
            // ok ...
        }
    });

    expect($this->kernel->run())->to->be->false;
});

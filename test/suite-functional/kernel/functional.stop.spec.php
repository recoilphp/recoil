<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Kernel\Exception\KernelStoppedException;
use Recoil\Kernel\Strand;

it('causes wait() to return false', function () {
    $this->kernel->execute(function () {
        yield;
        assert(false, 'not stopped');
    });
    $this->kernel->execute(function () {
        $this->kernel->stop();

        return;
        yield;
    });

    expect($this->kernel->wait())->to->be->false;
});

it('causes waitForStrand() to throw', function () {
    $strand = $this->kernel->execute(function () {
        yield;
        assert(false, 'not stopped');
    });
    $this->kernel->execute(function () {
        $this->kernel->stop();

        return;
        yield;
    });

    try {
        $this->kernel->waitForStrand($strand);
        assert(false, 'expected exception was not thrown');
    } catch (KernelStoppedException $e) {
        // ok ...
    }
});

it('causes waitFor() to throw', function () {
    $this->kernel->execute(function () {
        $this->kernel->stop();

        return;
        yield;
    });

    try {
        $this->kernel->waitFor(function () {
            yield;
            assert(false, 'not stopped');
        });
        assert(false, 'expected exception was not thrown');
    } catch (KernelStoppedException $e) {
        // ok ...
    }
});

it('causes all nested wait[For[Strand]]() calls to return/throw', function () {
    $this->kernel->execute(function () {
        $strand = yield Recoil::execute(function () {
            try {
                $this->kernel->waitFor(function () {
                    $this->kernel->stop();

                    return;
                    yield;
                });
                assert(false, 'expected exception was not thrown');
            } catch (KernelStoppedException $e) {
                // ok ...
            }

            return;
            yield;
        });

        try {
            $this->kernel->waitForStrand($strand);
            assert(false, 'expected exception was not thrown');
        } catch (KernelStoppedException $e) {
            // ok ...
        }
    });

    expect($this->kernel->wait())->to->be->false;
});

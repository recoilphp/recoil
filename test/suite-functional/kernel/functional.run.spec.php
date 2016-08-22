<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

describe('->run()', function () {
    it('waits for all strands to exit', function () {
        $this->kernel->execute(function () {
            echo 'a';

            return;
            yield;
        });
        $this->kernel->execute(function () {
            echo 'b';

            return;
            yield;
        });

        ob_start();
        $this->kernel->run();
        expect(ob_get_clean())->to->equal('ab');
    });

    it('returns immediately if kernel is already running', function () {
        $this->kernel->execute(function () {
            yield;
            $this->kernel->run();
            // if this test fails, this function will block forever
        });

        $this->kernel->run();
    });
});

it('stop() causes run() to return', function () {
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

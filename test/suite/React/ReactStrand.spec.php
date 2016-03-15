<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use Eloquent\Phony\Phony;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Kernel;
use Throwable;

describe(ReactStrand::class, function () {

    beforeEach(function () {
        $this->kernel = Phony::mock(Kernel::class);
        $this->api = Phony::mock(Api::class);

        $this->subject = new ReactStrand(
            1,
            $this->kernel->mock(),
            $this->api->mock()
        );
    });

    it('resolves the promise when the strand completes', function () {
        $resolve = Phony::spy();
        $reject = Phony::spy();
        $promise = $this->subject->promise();
        $promise->then($resolve, $reject);

        $this->subject->start(function () {
            return '<value>';
            yield;
        });

        $resolve->calledWith('<value>');
    });

    it('rejects the promise when the strand fails', function () {
        $resolve = Phony::spy();
        $reject = Phony::spy();
        $promise = $this->subject->promise();
        $promise->then($resolve, $reject);

        $exception = Phony::mock(Throwable::class);

        $this->subject->start(function () use ($exception) {
            throw $exception->mock();
            yield;
        });

        $reject->calledWith($exception);
    });

    it('rejects the promise when the strand is terminated', function () {
        $resolve = Phony::spy();
        $reject = Phony::spy();
        $promise = $this->subject->promise();
        $promise->then($resolve, $reject);

        $this->subject->terminate();

        $reject->calledWith(new TerminatedException($this->subject));
    });

});

<?php

declare(strict_types=1); // @codeCoverageIgnore

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
    });

    it('resolves the promise when the strand succeeds', function () {
        $this->subject = new ReactStrand(
            $this->kernel->get(),
            $this->api->get(),
            1,
            function () {
                return '<value>';
                yield;
            }
        );

        $resolve = Phony::spy();
        $reject = Phony::spy();
        $promise = $this->subject->promise();
        $promise->then($resolve, $reject);

        $this->subject->start();

        $resolve->calledWith('<value>');
    });

    it('rejects the promise when the strand fails', function () {
        $exception = Phony::mock(Throwable::class);

        $this->subject = new ReactStrand(
            $this->kernel->get(),
            $this->api->get(),
            1,
            function () use ($exception) {
                throw $exception->get();
                yield;
            }
        );

        $resolve = Phony::spy();
        $reject = Phony::spy();
        $promise = $this->subject->promise();
        $promise->then($resolve, $reject);

        $this->subject->start();

        $reject->calledWith($exception);
    });

    it('rejects the promise when the strand is terminated', function () {
        $this->subject = new ReactStrand(
            $this->kernel->get(),
            $this->api->get(),
            1,
            '<coroutine>'
        );

        $resolve = Phony::spy();
        $reject = Phony::spy();
        $promise = $this->subject->promise();
        $promise->then($resolve, $reject);

        $this->subject->terminate();

        $reject->calledWith(new TerminatedException($this->subject));
    });
});

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use Eloquent\Phony\Phony;
use React\Promise\Deferred;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Strand;
use Throwable;

describe(DeferredResolver::class, function () {

    beforeEach(function () {
        $this->deferred = Phony::mock(Deferred::class);
        $this->strand = Phony::mock(Strand::class);
        $this->strand->id->returns(1);

        $this->subject = new DeferredResolver(
            $this->deferred->mock()
        );
    });

    it('resolves the deferred when a strand completes', function () {
        $this->subject->success(
            $this->strand->mock(),
            '<value>'
        );

        $this->deferred->resolve->calledWith('<value>');
    });

    it('rejects the deferred when a strand fails', function () {
        $exception = Phony::mock(Throwable::class);

        $this->subject->failure(
            $this->strand->mock(),
            $exception->mock()
        );

        $this->deferred->reject->calledWith($exception);
    });

    it('rejects the deferred when a strand is terminated', function () {
        $this->subject->terminated(
            $this->strand->mock()
        );

        $this->deferred->reject->calledWith(
            new TerminatedException($this->strand->mock())
        );
    });

});

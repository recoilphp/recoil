<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use Eloquent\Phony\Phony;
use React\Promise\Deferred;
use Recoil\Kernel\Strand;
use Throwable;

describe(DeferredAdaptor::class, function () {

    beforeEach(function () {
        $this->deferred = Phony::mock(Deferred::class);
        $this->strand = Phony::mock(Strand::class);
        $this->strand->id->returns(1);

        $this->subject = new DeferredAdaptor(
            $this->deferred->mock()
        );
    });

    it('resolves the deferred when a strand succeeds', function () {
        $this->subject->resume(
            '<value>',
            $this->strand->mock()
        );

        $this->deferred->resolve->calledWith('<value>');
    });

    it('rejects the deferred when a strand fails', function () {
        $exception = Phony::mock(Throwable::class);

        $this->subject->throw(
            $exception->mock(),
            $this->strand->mock()
        );

        $this->deferred->reject->calledWith($exception);
    });

});

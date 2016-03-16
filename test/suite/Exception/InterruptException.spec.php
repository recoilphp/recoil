<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Exception;

use Eloquent\Phony\Phony;
use Recoil\Kernel\Strand;
use Throwable;

describe(InterruptException::class, function () {

    beforeEach(function () {
        $this->strand = Phony::mock(Strand::class);
        $this->strand->id->returns(123);

        $this->previous = Phony::mock(Throwable::class);
        $this->subject = new InterruptException(
            $this->strand->mock(),
            $this->previous->mock()
        );
    });

    it('produces a useful message', function () {
        expect($this->subject->getMessage())->to->equal('Strand #123 failed due to an uncaught exception.');
    });

    it('exposes the failed strand', function () {
        expect($this->subject->strand())->to->equal($this->strand->mock());
    });

    it('exposes the previous exception', function () {
        expect($this->subject->getPrevious())->to->equal($this->previous->mock());
    });

});

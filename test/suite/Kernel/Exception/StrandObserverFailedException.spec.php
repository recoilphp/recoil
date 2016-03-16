<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

use Eloquent\Phony\Phony;
use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandObserver;
use Throwable;

describe(StrandObserverFailedException::class, function () {

    beforeEach(function () {
        $this->strand = Phony::mock(Strand::class);
        $this->strand->id->returns(123);

        $this->observer = Phony::mock(StrandObserver::class);

        $this->previous = Phony::mock(Throwable::class);
        $this->subject = new StrandObserverFailedException(
            $this->strand->mock(),
            $this->observer->mock(),
            $this->previous->mock()
        );
    });

    it('produces a useful message', function () {
        expect($this->subject->getMessage())->to->equal(
            'Uncaught exception in strand observer for strand #123.'
        );
    });

    it('exposes the exited strand', function () {
        expect($this->subject->strand())->to->equal($this->strand->mock());
    });

    it('exposes the offending observer', function () {
        expect($this->subject->observer())->to->equal($this->observer->mock());
    });

    it('exposes the previous exception', function () {
        expect($this->subject->getPrevious())->to->equal($this->previous->mock());
    });

});

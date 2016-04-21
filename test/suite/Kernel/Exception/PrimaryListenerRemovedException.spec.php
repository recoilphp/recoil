<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

use Eloquent\Phony\Phony;
use Recoil\Kernel\Listener;
use Recoil\Kernel\Strand;

describe(PrimaryListenerRemovedException::class, function () {

    beforeEach(function () {
        $this->listener = Phony::mock(Listener::class);
        $this->strand = Phony::mock(Strand::class);
        $this->strand->id->returns(123);

        $this->subject = new PrimaryListenerRemovedException(
            $this->listener->mock(),
            $this->strand->mock()
        );
    });

    it('produces a useful message', function () {
        expect($this->subject->getMessage())->to->equal(
            'Primary listener removed from strand #123.'
        );
    });

    it('exposes the listener', function () {
        expect($this->subject->listener())->to->equal($this->listener->mock());
    });

    it('exposes the strand', function () {
        expect($this->subject->strand())->to->equal($this->strand->mock());
    });

});

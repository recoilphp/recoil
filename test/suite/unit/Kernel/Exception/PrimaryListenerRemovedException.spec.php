<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

use Eloquent\Phony\Phony;
use Recoil\Listener;
use Recoil\Strand;

describe(PrimaryListenerRemovedException::class, function () {
    beforeEach(function () {
        $this->listener = Phony::mock(Listener::class);
        $this->strand = Phony::mock(Strand::class);
        $this->strand->id->returns(123);

        $this->subject = new PrimaryListenerRemovedException(
            $this->listener->get(),
            $this->strand->get()
        );
    });

    it('produces a useful message', function () {
        expect($this->subject->getMessage())->to->equal(
            'Primary listener removed from strand #123.'
        );
    });

    it('exposes the listener', function () {
        expect($this->subject->listener())->to->equal($this->listener->get());
    });

    it('exposes the strand', function () {
        expect($this->subject->strand())->to->equal($this->strand->get());
    });
});

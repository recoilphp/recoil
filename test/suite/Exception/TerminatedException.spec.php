<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Exception;

use Eloquent\Phony\Phony;
use Recoil\Kernel\Strand;

describe(TerminatedException::class, function () {
    beforeEach(function () {
        $this->strand = Phony::mock(Strand::class);
        $this->strand->id->returns(123);

        $this->subject = new TerminatedException($this->strand->get());
    });

    it('produces a useful message', function () {
        expect($this->subject->getMessage())->to->equal('Strand #123 was terminated.');
    });

    it('exposes the terminated strand', function () {
        expect($this->subject->strand())->to->equal($this->strand->get());
    });
});

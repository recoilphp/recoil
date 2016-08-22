<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Exception;

use Eloquent\Phony\Phony;
use Throwable;

describe(CompositeException::class, function () {
    it('accepts multiple previous exceptions', function () {
        $exception1 = Phony::mock(Throwable::class)->get();
        $exception2 = Phony::mock(Throwable::class)->get();

        $exceptions = [
            1 => $exception1,
            0 => $exception2,
        ];

        $exception = new CompositeException($exceptions);

        expect($exception->getMessage())->to->equal('Multiple exceptions occurred.');
        expect($exception->exceptions() === $exceptions)->to->be->true;
    });
});

<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

describe(RejectedException::class, function () {
    it('uses string rejection reasons as the exception message', function () {
        $exception = new RejectedException('<string>');

        expect($exception->getMessage())->to->equal('<string>');
    });

    it('uses integer rejection reasons as the exception code', function () {
        $exception = new RejectedException(123);

        expect($exception->getMessage())->to->equal('The promise was rejected (123).');
        expect($exception->getCode())->to->equal(123);
    });

    it('produces a useful message for other rejection reasons', function () {
        $exception = new RejectedException([1, 2, 3]);

        expect($exception->getMessage())->to->equal(
            'The promise was rejected ([1, 2, 3]).'
        );
    });

    it('exposes the reason', function () {
        $exception = new RejectedException([1, 2, 3]);

        expect($exception->reason())->to->equal([1, 2, 3]);
    });
});

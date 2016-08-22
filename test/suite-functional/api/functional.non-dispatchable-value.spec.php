<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use UnexpectedValueException;

rit('invoked by yielding an non-dispatchable-value with no key', function () {
    $strand = yield Recoil::execute(function () {
        yield '<string>';
    });

    try {
        yield Recoil::adopt($strand);
    } catch (UnexpectedValueException $e) {
        expect($e->getMessage())->to->equal(
            'The yielded pair (0, "<string>") does not describe any known operation.'
        );
    }
});

rit('invoked by yielding an non-dispatchable-value with a key', function () {
    $strand = yield Recoil::execute(function () {
        yield 123 => '<string>';
    });

    try {
        yield Recoil::adopt($strand);
    } catch (UnexpectedValueException $e) {
        expect($e->getMessage())->to->equal(
            'The yielded pair (123, "<string>") does not describe any known operation.'
        );
    }
});

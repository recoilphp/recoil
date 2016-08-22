<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Recoil\Kernel\Strand;

rit('runs a coroutine in a new strand', function () {
    ob_start();

    yield Recoil::execute(function () {
        echo 'b';

        return;
        yield;
    });

    echo 'a';
    yield;

    expect(ob_get_clean())->to->equal('ab');
});

rit('allows the strand to be terminated immediately', function () {
    $strand = yield Recoil::execute(function () {
        expect(false)->to->be->ok('strand was not terminated');
        yield;
    });

    $strand->terminate();

    yield;
});

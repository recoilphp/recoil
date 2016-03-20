<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Recoil\Kernel\Strand;

rit('creates a callback that runs a coroutine in a new strand', function () {
    ob_start();

    $fn = yield Recoil::callback(function () {
        echo 'c';

        return;
        yield;
    });

    echo 'a';
    $fn();
    echo 'b';

    yield;

    expect(ob_get_clean())->to->equal('abc');
});

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;
use Recoil\Kernel\Strand;

rit('creates a callback that runs a coroutine in a new strand', function () {
    $spy = Phony::spy(function () {
        return;
        yield;
    });

    $fn = yield Recoil::callback($spy);
    expect($fn)->to->satisfy('is_callable');

    $spy->never()->called();

    $fn();

    $spy->never()->called();

    yield;

    $spy->called();
});

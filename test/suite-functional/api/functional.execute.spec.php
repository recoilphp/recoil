<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;
use Recoil\Kernel\Strand;

rit('runs a coroutine in a new strand', function () {
    $spy = Phony::spy(function () {
        return '<ok>';
        yield;
    });

    $strand = yield Recoil::execute($spy);
    expect($strand)->to->be->an->instanceof(Strand::class);

    $spy->never()->called();

    expect(yield $strand)->to->equal('<ok>');
});

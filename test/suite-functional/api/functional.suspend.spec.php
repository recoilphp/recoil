<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

rit('does not resume automatically', function () {
    yield Recoil::suspend();
    assert(false, 'strand was resumed');
});

rit('passes the strand to the given callback', function () {
    $expected = yield Recoil::strand();
    $strand = yield Recoil::suspend(function ($strand) {
        $strand->resume($strand);
    });

    expect($strand)->to->equal($expected);
});

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

rit('suspends the calling strand', function () {
    yield Recoil::suspend();
    assert(false, 'strand was not suspended');
});

rit('passes the strand to the given callback', function () {
    $expected = yield Recoil::strand();
    $strand = yield Recoil::suspend(function ($strand) {
        $strand->resume($strand);
    });

    expect($strand)->to->equal($expected);
});

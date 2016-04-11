<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

rit('terminates the calling strand', function () {
    $strand = yield Recoil::execute(function () {
        yield Recoil::terminate();
        assert(false, 'strand was not terminated');
    });

    yield;

    expect($strand->hasExited())->to->be->true;
});

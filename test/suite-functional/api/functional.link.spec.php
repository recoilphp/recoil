<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

rit('terminates the linked strand', function () {
    $strand = yield Recoil::execute(function () {
        yield 10;
        expect(false)->to->be->ok('strand was not terminated');
    });

    yield Recoil::link($strand);
    yield Recoil::terminate();
});

rit('does not terminate an unlinked strand', function () {
    $strand = yield Recoil::execute(function () {
        yield;
    });

    yield Recoil::link($strand);
    yield Recoil::unlink($strand);
    yield Recoil::terminate();
    expect($strand->hasExited())->to->be->false;
});

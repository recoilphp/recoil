<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

rit('suspends the calling strand', function () {
    $suspending = false;
    $strand = yield Recoil::execute(function () use (&$suspending) {
        $suspending = true;
        yield Recoil::suspend();
        expect(false)->to->be->ok('strand was not suspended');
    });

    yield; // yield once to allow the other strand to run

    expect($suspending)->to->be->true;

    yield; // another time to ensure it isn't resumed

    expect($strand->hasExited())->to->be->false;
});

rit('passes the strand to the given callback', function () {
    $expected = yield Recoil::strand();
    $strand = yield Recoil::suspend(function ($strand) {
        $strand->send($strand);
    });

    expect($strand)->to->equal($expected);
});

rit('invokes the terminate callback if the strand is terminated', function () {
    $strand = yield Recoil::execute(function () {
        $expected = yield Recoil::strand();
        yield Recoil::suspend(
            null,
            function ($strand) use ($expected) {
                expect($strand)->to->equal($expected);
            }
        );
        expect(false)->to->be->ok('strand was not terminated');
    });

    yield;
    $strand->terminate();
});

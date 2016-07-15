<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;

rit('suspends the calling strand', function () {
    $suspending = false;
    $strand = yield Recoil::execute(function () use (&$suspending) {
        $suspending = true;
        yield Recoil::suspend();
        assert(false, 'strand was not suspended');
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
        assert(false, 'strand was not terminated');
    });

    yield;
    $strand->terminate();
});

rit('can be resumed', function () {
    $resumed = false;
    $strand = yield Recoil::execute(function () use (&$resumed) {
        yield Recoil::suspend();
        $resumed = true;
    });

    yield; // yield once to allow the other strand to run

    yield Recoil::resume($strand);

    expect($resumed)->to->be->true;
});

rit('can be resumed with a value', function () {
    $strand = yield Recoil::execute(function () {
        return yield Recoil::suspend();
    });

    yield; // yield to allow the other strand to run

    yield Recoil::resume($strand, '<value>');

    expect(yield $strand)->to->equal('<value>');
});

rit('can be resumed with error', function () {
    $strand = yield Recoil::execute(function () {
        try {
            yield Recoil::suspend();
        } catch (Exception $e) {
            return $e;
        }
    });

    yield; // yield to allow the other strand to run

    $exception = new Exception('<exception>');
    yield Recoil::throw($strand, $exception);

    expect(yield $strand)->to->equal($exception);
});

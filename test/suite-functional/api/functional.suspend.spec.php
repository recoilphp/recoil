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

context('when resumed with a value', function () {
    rit('executes the resumed strand before resume() returns', function () {
        $suspended = false;
        $strand = yield Recoil::execute(function () use (&$suspended) {
            $suspended = true;
            yield Recoil::suspend();
            $suspended = false;
        });

        yield; // yield once to allow the other strand to run

        expect($suspended)->to->be->true;

        yield Recoil::resume($strand);

        expect($suspended)->to->be->false;
    });

    rit('receives the value', function () {
        $value = null;
        $strand = yield Recoil::execute(function () use (&$value) {
            $value = yield Recoil::suspend();
        });

        yield; // yield to allow the other strand to run

        yield Recoil::resume($strand, '<value>');

        expect($value)->to->equal('<value>');
    });
});

context('when resumed with an error', function () {
    rit('executes the resumed strand before resume() returns', function () {
        $suspended = false;
        $strand = yield Recoil::execute(function () use (&$suspended) {
            try {
                $suspended = true;
                yield Recoil::suspend();
            } catch (Exception $e) {
                $suspended = false;
            }
        });

        yield; // yield once to allow the other strand to run

        expect($suspended)->to->be->true;

        yield Recoil::throw($strand, new Exception('<exception>'));

        expect($suspended)->to->be->false;
    });

    rit('receives the exception', function () {
        $exception = null;
        $strand = yield Recoil::execute(function () use (&$exception) {
            try {
                yield Recoil::suspend();
            } catch (Exception $e) {
                $exception = $e;
            }
        });

        yield; // yield to allow the other strand to run

        $expected = new Exception('<exception>');
        yield Recoil::throw($strand, $expected);

        expect($exception)->to->equal($expected);
    });
});

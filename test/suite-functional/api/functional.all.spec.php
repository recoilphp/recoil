<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Exception\TerminatedException;

rit('executes the coroutines', function () {
    ob_start();
    yield Recoil::all(
        function () {
            echo 'a';

            return; yield;
        },
        function () {
            echo 'b';

            return; yield;
        }
    );
    expect(ob_get_clean())->to->equal('ab');
});

rit('can be invoked by yielding an array', function () {
    ob_start();
    yield [
        function () {
            echo 'a';

            return; yield;
        },
        function () {
            echo 'b';

            return; yield;
        },
    ];
    expect(ob_get_clean())->to->equal('ab');
});

rit('returns an array of return values', function () {
    expect(yield Recoil::all(
        function () { return 'a'; yield; },
        function () { return 'b'; yield; }
    ))->to->equal([
        0 => 'a',
        1 => 'b',
    ]);
});

rit('sorts the array based on the order that the substrands exit', function () {
    expect(yield Recoil::all(
        function () {
            yield 0.02;

            return 'a';
        },
        function () {
            yield 0.01;

            return 'b';
        }
    ))->to->equal([
        1 => 'b',
        0 => 'a',
    ]);
});

rit('terminates the substrands when the calling strand is terminated', function () {
    $strand = yield Recoil::execute(function () {
        yield (function () {
            yield Recoil::all(
                function () { yield; assert(false, 'not terminated'); },
                function () { yield; assert(false, 'not terminated'); }
            );
        })();
    });

    yield;

    $strand->terminate();
});

context('when one of the substrands fails', function () {
    rit('resumes the calling strand with the exception', function () {
        try {
            yield Recoil::all(
                function () { return; yield; },
                function () { throw new Exception('<exception>'); yield; }
            );
            assert(false, 'Expected exception was not thrown.');
        } catch (Exception $e) {
            expect($e->getMessage())->to->equal('<exception>');
        }
    });

    rit('terminates the remaining strands', function () {
        try {
            yield Recoil::all(
                function () { yield; assert(false, 'not terminated'); },
                function () { throw new Exception('<exception>'); yield; }
            );
        } catch (Exception $e) {
            // fall-through ...
        }
    });
});

context('when one of the substrands is terminated', function () {
    rit('resumes the calling strand with an exception', function () {
        $id = null;
        try {
            yield Recoil::all(
                function () { return; yield; },
                function () use (&$id) {
                    $id = (yield Recoil::strand())->id();
                    yield Recoil::terminate();
                }
            );
            assert(false, 'Expected exception was not thrown.');
        } catch (TerminatedException $e) {
            expect($e->getMessage())->to->equal("Strand #$id was terminated.");
        }
    });

    rit('terminates the remaining strands', function () {
        try {
            yield Recoil::all(
                function () { yield; assert(false, 'not terminated'); },
                function () { yield Recoil::terminate(); }
            );
        } catch (TerminatedException $e) {
            // ok ...
        }
    });
});

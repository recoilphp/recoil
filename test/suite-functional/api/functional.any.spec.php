<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Exception\CompositeException;
use Recoil\Exception\TerminatedException;

rit('executes the coroutines', function () {
    ob_start();
    yield Recoil::any(
        function () {
            echo 'a';
            yield;
        },
        function () {
            echo 'b';

            return;
            yield;
        }
    );
    expect(ob_get_clean())->to->equal('ab');
});

rit('terminates the substrands when the calling strand is terminated', function () {
    $strand = yield Recoil::execute(function () {
        yield (function () {
            yield Recoil::any(
                function () { yield; assert(false, 'not terminated'); },
                function () { yield; assert(false, 'not terminated'); }
            );
        })();
    });

    yield;

    $strand->terminate();
});

context('when one of the substrands succeeds', function () {
    rit('resumes the calling strand with the return value', function () {
        expect(yield Recoil::any(
            function () {
                yield;

                return 'a';
            },
            function () {
                return 'b';
                yield;
            }
        ))->to->equal('b');
    });

    xit('terminates the remaining strands', function () {
        yield Recoil::any(
            function () {
                yield;
                assert(false, 'not terminated');
            },
            function () {
                return;
                yield;
            }
        );
    });
});

context('when all of the substrands fail or are terminated', function () {
    rit('resumes the calling strand with a composite exception', function () {
        try {
            yield Recoil::any(
                function () { yield Recoil::terminate(); },
                function () { throw new Exception('<exception>'); yield; }
            );
            assert(false, 'expected exception was not thrown');
        } catch (CompositeException $e) {
            expect($e->exceptions())->to->have->length(2);
            expect($e->exceptions()[0])->to->be->an->instanceof(TerminatedException::class);
            expect($e->exceptions()[1])->to->be->an->instanceof(Exception::class);
        }
    });

    rit('sorts the previous exceptions based on the order that the substrands exit', function () {
        try {
            yield Recoil::any(
                function () { yield; yield; throw new Exception('<exception-a>'); },
                function () { yield; throw new Exception('<exception-b>'); }
            );
            assert(false, 'expected exception was not thrown');
        } catch (CompositeException $e) {
            expect(array_keys($e->exceptions()))->to->equal([1, 0]);
        }
    });
});

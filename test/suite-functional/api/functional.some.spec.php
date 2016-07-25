<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use InvalidArgumentException;
use Recoil\Exception\CompositeException;
use Recoil\Exception\TerminatedException;

rit('executes the coroutines', function () {
    ob_start();
    yield Recoil::some(
        3,
        function () {
            echo 'a';
            yield;
        },
        function () {
            echo 'b';
            yield;
        },
        function () {
            echo 'c';
            yield;
        }
    );
    expect(ob_get_clean())->to->equal('abc');
});

rit('terminates the substrands when the calling strand is terminated', function () {
    $strand = yield Recoil::execute(function () {
        yield (function () {
            yield Recoil::some(
                2,
                function () { yield; expect(false)->to->be->ok('strand was not terminated'); },
                function () { yield; expect(false)->to->be->ok('strand was not terminated'); }
            );
        })();
    });

    yield;

    $strand->terminate();
});

rit('throws when the count is zero', function () {
    try {
        yield Recoil::some(
            0,
            function () {},
            function () {}
        );
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->to->equal(
            'Can not wait for 0 coroutines, count must be between 1 and 2, inclusive.'
        );
    }
});

rit('throws when the count is negative', function () {
    try {
        yield Recoil::some(
            -1,
            function () {},
            function () {}
        );
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->to->equal(
            'Can not wait for -1 coroutines, count must be between 1 and 2, inclusive.'
        );
    }
});

rit('throws when the count is greater than the number of coroutines', function () {
    try {
        yield Recoil::some(
            3,
            function () {},
            function () {}
        );
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->to->equal(
            'Can not wait for 3 coroutines, count must be between 1 and 2, inclusive.'
        );
    }
});

context('when the required number of substrands succeed', function () {
    rit('returns an array of coroutine return values', function () {
        expect(yield Recoil::some(
            2,
            function () {
                yield;

                return 'a';
            },
            function () {
                return 'b';
                yield;
            },
            function () {
                return 'c';
                yield;
            }
        ))->to->equal([
            1 => 'b',
            2 => 'c',
        ]);
    });

    rit('terminates the remaining strands', function () {
        yield Recoil::some(
            1,
            function () {
                yield;
                expect(false)->to->be->ok('strand was not terminated');
            },
            function () {
                return;
                yield;
            }
        );
    });
});

context('when too many substrands fail', function () {
    rit('throws a composite exception', function () {
        try {
            yield Recoil::some(
                2,
                function () { yield Recoil::terminate(); },
                function () { throw new Exception('<exception>'); yield; },
                function () { yield; }
            );
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (CompositeException $e) {
            expect($e->exceptions())->to->have->length(2);
            expect($e->exceptions()[0])->to->be->an->instanceof(TerminatedException::class);
            expect($e->exceptions()[1])->to->be->an->instanceof(Exception::class);
        }
    });

    rit('sorts the previous exceptions based on the order that the substrands exit', function () {
        try {
            yield Recoil::some(
                2,
                function () { yield; yield; throw new Exception('<exception-a>'); },
                function () { yield; throw new Exception('<exception-b>'); },
                function () { yield; }
            );
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (CompositeException $e) {
            expect(array_keys($e->exceptions()))->to->equal([1, 0]);
        }
    });
});

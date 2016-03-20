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
                yield 0.02;

                return 'a';
            },
            function () {
                yield 0.01;

                return 'b';
            }
        ))->to->equal('b');
    });

    xit('terminates the remaining strands', function () {
        yield Recoil::any(
            function () {
                yield 0.02;
                assert(false, 'not terminated');
            },
            function () {
                yield 0.01;

                return 'b';
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
            assert(false, 'Expected exception was not thrown.');
        } catch (CompositeException $e) {
            expect($e->exceptions())->to->have->length(2);
            expect($e->exceptions()[0])->to->be->an->instanceof(TerminatedException::class);
            expect($e->exceptions()[1])->to->be->an->instanceof(Exception::class);
        }
    });

    rit('sorts the previous exceptions based on the order that the substrands exit', function () {
        try {
            yield Recoil::any(
                function () { yield 0.02; throw new Exception('<exception-a>'); },
                function () { yield 0.01; throw new Exception('<exception-b>'); }
            );
            assert(false, 'Expected exception was not thrown.');
        } catch (CompositeException $e) {
            expect(array_keys($e->exceptions()))->to->equal([1, 0]);
        }
    });
});

// context('when one of the substrands fails', function () {
//     rit('resumes the calling strand with the exception', function () {
//         try {
//             yield Recoil::any(
//                 function () { return; yield; },
//                 function () { throw new Exception('<exception>'); yield; }
//             );
//             assert(false, 'Expected exception was not thrown.');
//         } catch (Exception $e) {
//             expect($e->getMessage())->to->equal('<exception>');
//         }
//     });

//     rit('terminates the remaining strands', function () {
//         try {
//             yield Recoil::any(
//                 function () { yield; assert(false, 'not terminated'); },
//                 function () { throw new Exception('<exception>'); yield; }
//             );
//         } catch (Exception $e) {
//             // fall-through ...
//         }
//     });
// });

// context('when one of the substrands is terminated', function () {
//     rit('resumes the calling strand with an exception', function () {
//         $id = null;
//         try {
//             yield Recoil::any(
//                 function () { return; yield; },
//                 function () use (&$id) {
//                     $id = (yield Recoil::strand())->id();
//                     yield Recoil::terminate();
//                 }
//             );
//             assert(false, 'Expected exception was not thrown.');
//         } catch (TerminatedException $e) {
//             expect($e->getMessage())->to->equal("Strand #$id was terminated.");
//         }
//     });

//     rit('terminates the remaining strands', function () {
//         try {
//             yield Recoil::any(
//                 function () { yield; assert(false, 'not terminated'); },
//                 function () { yield Recoil::terminate(); }
//             );
//         } catch (TerminatedException $e) {
//             // ok ...
//         }
//     });
// });

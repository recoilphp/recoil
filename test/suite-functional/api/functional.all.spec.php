<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Exception\TerminatedException;

rit('executes the coroutines', function () {
    ob_start();
    yield Recoil::all(
        function () {
            echo 'a';

            return;
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

rit('can be invoked by yielding an array', function () {
    ob_start();
    yield [
        function () {
            echo 'a';

            return;
            yield;
        },
        function () {
            echo 'b';

            return;
            yield;
        },
    ];
    expect(ob_get_clean())->to->equal('ab');
});

rit('returns an array of coroutine return values', function () {
    expect(yield Recoil::all(
        function () {
            return 'a';
            yield;
        },
        function () {
            return 'b';
            yield;
        }
    ))->to->equal([
        0 => 'a',
        1 => 'b',
    ]);
});

rit('sorts the array based on the order that the substrands exit', function () {
    expect(yield Recoil::all(
        function () {
            yield;

            return 'a';
        },
        function () {
            return 'b';
            yield;
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
                function () {
                    yield;
                    expect(false)->to->be->ok('strand was not terminated');
                },
                function () {
                    yield;
                    expect(false)->to->be->ok('strand was not terminated');
                }
            );
        })();
    });

    yield;

    $strand->terminate();
});

context('when one of the substrands fails', function () {
    rit('propagates the exception', function () {
        try {
            yield Recoil::all(
                function () {
                    return;
                    yield;
                },
                function () {
                    throw new Exception('<exception>');
                    yield;
                }
            );
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (Exception $e) {
            expect($e->getMessage())->to->equal('<exception>');
        }
    });

    rit('terminates the remaining strands', function () {
        try {
            yield Recoil::all(
                function () {
                    yield;
                    expect(false)->to->be->ok('strand was not terminated');
                },
                function () {
                    throw new Exception('<exception>');
                    yield;
                }
            );
        } catch (Exception $e) {
            // ok ...
        }
    });
});

context('when one of the substrands is terminated', function () {
    rit('throws an exception', function () {
        $id = null;
        try {
            yield Recoil::all(
                function () {
                    return;
                    yield;
                },
                function () use (&$id) {
                    $id = (yield Recoil::strand())->id();
                    yield Recoil::terminate();
                }
            );
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (TerminatedException $e) {
            expect($e->getMessage())->to->equal("Strand #$id was terminated.");
        }
    });

    rit('terminates the remaining strands', function () {
        try {
            yield Recoil::all(
                function () {
                    yield;
                    expect(false)->to->be->ok('strand was not terminated');
                },
                function () {
                    yield Recoil::terminate();
                }
            );
        } catch (TerminatedException $e) {
            // ok ...
        }
    });
});

<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

context('when called with 1 parameter', function () {
    context('when a strand exits', function () {
        rit('terminates the linked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () use ($strandA) {
                yield Recoil::link($strandA);
            });

            yield;

            expect($strandA->hasExited())->to->be->true;
            expect($strandB->hasExited())->to->be->true;
        });

        rit('does not terminate unlinked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () use ($strandA) {
                yield Recoil::link($strandA);
                yield Recoil::unlink($strandA);
            });

            yield;

            expect($strandA->hasExited())->to->be->false;
            expect($strandB->hasExited())->to->be->true;

            $strandA->terminate();
        });
    });

    context('when a strand exits with an exception', function () {
        rit('terminates the linked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () use ($strandA) {
                yield Recoil::link($strandA);
                throw new \Exception('<fail>');
            });

            try {
                yield Recoil::adopt($strandB);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (\Exception $e) {
                yield;  // Allow linked strands to be terminated.
                expect($strandA->hasExited())->to->be->true;
                expect($strandB->hasExited())->to->be->true;
            }
        });

        rit('does not terminate unlinked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () use ($strandA) {
                yield Recoil::link($strandA);
                yield Recoil::unlink($strandA);
                throw new \Exception('<fail>');
            });

            try {
                yield Recoil::adopt($strandB);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (\Exception $e) {
                yield;  // Allow linked strands to be terminated.

                expect($strandA->hasExited())->to->be->false;
                expect($strandB->hasExited())->to->be->true;
            }

            $strandA->terminate();
        });
    });

    context('when a strand is terminated', function () {
        rit('terminates the linked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () use ($strandA) {
                yield Recoil::link($strandA);
                yield 10;
            });

            yield;
            $strandA->terminate();

            expect($strandA->hasExited())->to->be->true;
            expect($strandB->hasExited())->to->be->true;
        });

        rit('does not terminate unlinked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () use ($strandA) {
                yield Recoil::link($strandA);
                yield Recoil::unlink($strandA);
                yield 10;
            });

            yield;
            $strandA->terminate();

            expect($strandA->hasExited())->to->be->true;
            expect($strandB->hasExited())->to->be->false;

            $strandB->terminate();
        });
    });

    rit('creates bidirectional links', function () {
        $strandA = yield Recoil::execute(function () {
            yield 10;
        });

        $strandB = yield Recoil::execute(function () use ($strandA) {
            yield Recoil::link($strandA);
            yield 10;
        });

        yield;
        $strandB->terminate();

        expect($strandA->hasExited())->to->be->true;
        expect($strandB->hasExited())->to->be->true;
    });

    rit('breaks bidirectional links', function () {
        $strandA = yield Recoil::execute(function () {
            yield 10;
        });

        $strandB = yield Recoil::execute(function () use ($strandA) {
            yield Recoil::link($strandA);
            yield Recoil::unlink($strandA);
            yield 10;
        });

        yield;
        $strandB->terminate();

        expect($strandA->hasExited())->to->be->false;
        expect($strandB->hasExited())->to->be->true;

        $strandA->terminate();
    });

    rit('does not fail if linked to itself', function () {
        $strand = yield Recoil::execute(function () {
            yield Recoil::link(yield Recoil::strand());
            yield 10;
        });

        yield;
        $strand->terminate();

        expect($strand->hasExited())->to->be->true;
    });

    rit('does not fail if unlinked from itself', function () {
        $strand = yield Recoil::execute(function () {
            yield Recoil::link(yield Recoil::strand());
            yield Recoil::unlink(yield Recoil::strand());
            yield 10;
        });

        yield;
        $strand->terminate();

        expect($strand->hasExited())->to->be->true;
    });
});

context('when called with 2 parameters', function () {
    context('when a strand exits', function () {
        rit('terminates the linked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () {
                yield Recoil::suspend();
            });

            yield;
            yield Recoil::link($strandA, $strandB);
            yield $strandB->send();

            expect($strandA->hasExited())->to->be->true;
            expect($strandB->hasExited())->to->be->true;
        });

        rit('does not terminate unlinked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () {
                yield Recoil::suspend();
            });

            yield;
            yield Recoil::link($strandA, $strandB);
            yield Recoil::unlink($strandA, $strandB);
            yield $strandB->send();

            expect($strandA->hasExited())->to->be->false;
            expect($strandB->hasExited())->to->be->true;

            $strandA->terminate();
        });
    });

    context('when a strand exits with an exception', function () {
        rit('terminates the linked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () use ($strandA) {
                yield;
                throw new \Exception('<fail>');
            });

            yield;
            yield Recoil::link($strandA, $strandB);

            try {
                yield Recoil::adopt($strandB);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (\Exception $e) {
                yield;  // Allow linked strands to be terminated.
                expect($strandA->hasExited())->to->be->true;
                expect($strandB->hasExited())->to->be->true;
            }
        });

        rit('does not terminate unlinked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () use ($strandA) {
                yield;
                throw new \Exception('<fail>');
            });

            yield;
            yield Recoil::link($strandA, $strandB);
            yield Recoil::unlink($strandA, $strandB);

            try {
                yield Recoil::adopt($strandB);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (\Exception $e) {
                yield;  // Allow linked strands to be terminated.
                expect($strandA->hasExited())->to->be->false;
                expect($strandB->hasExited())->to->be->true;
            }

            $strandA->terminate();
        });
    });

    context('when a strand is terminated', function () {
        rit('terminates the linked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () {
                yield 10;
            });

            yield;
            yield Recoil::link($strandA, $strandB);
            $strandA->terminate();

            expect($strandA->hasExited())->to->be->true;
            expect($strandB->hasExited())->to->be->true;
        });

        rit('does not terminate unlinked strand', function () {
            $strandA = yield Recoil::execute(function () {
                yield 10;
            });

            $strandB = yield Recoil::execute(function () {
                yield 10;
            });

            yield;
            yield Recoil::link($strandA, $strandB);
            yield Recoil::unlink($strandA, $strandB);
            $strandA->terminate();

            expect($strandA->hasExited())->to->be->true;
            expect($strandB->hasExited())->to->be->false;

            $strandB->terminate();
        });
    });

    rit('creates bidirectional links', function () {
        $strandA = yield Recoil::execute(function () {
            yield 10;
        });

        $strandB = yield Recoil::execute(function () {
            yield 10;
        });

        yield;
        yield Recoil::link($strandA, $strandB);
        $strandB->terminate();

        expect($strandA->hasExited())->to->be->true;
        expect($strandB->hasExited())->to->be->true;
    });

    rit('breaks bidirectional links', function () {
        $strandA = yield Recoil::execute(function () {
            yield 10;
        });

        $strandB = yield Recoil::execute(function () {
            yield 10;
        });

        yield;
        yield Recoil::link($strandA, $strandB);
        yield Recoil::unlink($strandA, $strandB);
        $strandB->terminate();

        expect($strandA->hasExited())->to->be->false;
        expect($strandB->hasExited())->to->be->true;

        $strandA->terminate();
    });

    rit('does not fail if linked to itself', function () {
        $strand = yield Recoil::execute(function () {
            yield 10;
        });

        yield;
        yield Recoil::link($strand, $strand);
        $strand->terminate();

        expect($strand->hasExited())->to->be->true;
    });

    rit('does not fail if unlinked from itself', function () {
        $strand = yield Recoil::execute(function () {
            yield 10;
        });

        yield;
        yield Recoil::link($strand, $strand);
        yield Recoil::unlink($strand, $strand);
        $strand->terminate();

        expect($strand->hasExited())->to->be->true;
    });
});

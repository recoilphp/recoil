<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;
use Recoil\Kernel\Api;
use Recoil\Kernel\Strand;

context('kernel api', function () {

    describe('->execute()', function () {
        rit('runs a coroutine in a new strand', function () {
            $spy = Phony::spy(function () {
                return '<ok>';
                yield;
            });

            $strand = yield Recoil::execute($spy);
            expect($strand)->to->be->an->instanceof(Strand::class);

            $spy->never()->called();

            expect(yield $strand)->to->equal('<ok>');
        });
    });

    describe('->callback()', function () {
        rit('creates a callback that runs a coroutine in a new strand', function () {
            $spy = Phony::spy(function () {
                return;
                yield;
            });

            $fn = yield Recoil::callback($spy);
            expect($fn)->to->satisfy('is_callable');

            $spy->never()->called();

            $fn();

            $spy->never()->called();

            yield;

            $spy->called();
        });
    });

    describe('->cooperate()', function () {
        rit('yields control to another strand', function () {
            $spy = Phony::spy();

            yield Recoil::execute(function () use ($spy) {
                $spy(2);

                return;
                yield;
            });

            $spy(1);
            yield Recoil::cooperate();
            $spy(3);

            Phony::inOrder(
                $spy->calledWith(1),
                $spy->calledWith(2),
                $spy->calledWith(3)
            );
        });

        rit('can be invoked by yielding null', function () {
            $spy = Phony::spy();

            yield Recoil::execute(function () use ($spy) {
                $spy(2);

                return;
                yield;
            });

            $spy(1);
            yield;
            $spy(3);

            Phony::inOrder(
                $spy->calledWith(1),
                $spy->calledWith(2),
                $spy->calledWith(3)
            );
        });
    });

    describe('->sleep()', function () {
        rit('resumes execution after the specified number of seconds', function () {
            $time = microtime(true);
            yield Recoil::sleep(0.1);
            $diff = microtime(true) - $time;

            expect($diff)->to->be->within(0.075, 1.075);
        });

        rit('can be invoked by yielding a number', function () {
            $time = microtime(true);
            yield 0.05;
            $diff = microtime(true) - $time;

            expect($diff)->to->be->within(0.04, 0.06);
        });
    });

    xdescribe('->timeout()', function () {
        rit('returns coroutine value if it completes before the timeout', function () {
            $result = yield Recoil::timeout(
                1,
                function () {
                    // echo "DONE" . PHP_EOL; // FIXME
                    return '<ok>';
                    yield;
                }
            );

            expect($result)->to->equal('<ok>');
            // echo "HOKAY" . PHP_EOL; // FIXME
        });
    });

    it('can limit execution time with ::timeout()', function () {
        $result = yield Recoil::timeout(
            0.25,
            function () {
                echo 'running!';

                return '<ok>';
                yield;
            }
        );

        expect($result)->to->equal('<ok>');
    });
});

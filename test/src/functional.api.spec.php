<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;
use Recoil\Kernel\Strand;

context('kernel api', function () {

    rit('can spawn new strands with ::execute()', function () {
        $spy = Phony::spy(function () {
            return '<ok>';
            yield;
        });

        $strand = yield Recoil::execute($spy);
        expect($strand)->to->be->an->instanceof(Strand::class);

        $spy->never()->called();

        expect(yield $strand)->to->equal('<ok>');
    });

    rit('can spawn new strands with ::callback()', function () {
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

    rit('can explicitly yield to another strand', function () {
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

    rit('can sleep with ::sleep()', function () {
        $time = microtime(true);
        yield Recoil::sleep(0.1);
        $diff = microtime(true) - $time;

        expect($diff)->to->be->within(0.075, 1.075);
    });

    rit('can sleep by yielding a number', function () {
        $time = microtime(true);
        yield 0.1;
        $diff = microtime(true) - $time;

        expect($diff)->to->be->within(0.075, 1.075);
    });

    // @link https://github.com/recoilphp/recoil/issues/87
    xit('timeout', function () {
        $result = yield Recoil::timeout(
            1,
            function () {
                echo 'running!';

                return '<ok>';
                yield;
            }
        );

        expect($result)->to->equal('<ok>');
    });
});

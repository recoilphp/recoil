<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;
use Recoil\Kernel\Strand;

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

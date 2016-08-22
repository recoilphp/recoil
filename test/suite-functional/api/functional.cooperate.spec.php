<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Recoil\Kernel\Strand;

rit('yields control to another strand', function () {
    ob_start();

    yield Recoil::execute(function () {
        echo 'b';

        return;
        yield;
    });

    echo 'a';
    yield Recoil::cooperate();
    echo 'c';

    expect(ob_get_clean())->to->equal('abc');
});

rit('can be invoked by yielding null', function () {
    ob_start();

    yield Recoil::execute(function () {
        echo 'b';

        return;
        yield;
    });

    echo 'a';
    yield;
    echo 'c';

    expect(ob_get_clean())->to->equal('abc');
});

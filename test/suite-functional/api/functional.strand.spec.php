<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

rit('returns the calling strands', function () {
    $strand = null;
    $expected = yield Recoil::execute(function () use (&$strand) {
        $strand = yield Recoil::strand();
    });

    yield $expected;

    expect($strand)->to->equal($expected);
});

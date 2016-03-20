<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

rit('resumes the calling strand with itself', function () {
    $strand = null;
    $expected = yield Recoil::execute(function () use (&$strand) {
        $strand = yield Recoil::strand();
    });

    yield $expected;

    expect($strand)->to->equal($expected);
});

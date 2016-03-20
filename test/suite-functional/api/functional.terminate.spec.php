<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Recoil\Exception\TerminatedException;

rit('terminates the calling strand', function () {
    try {
        yield (function () {
            yield Recoil::terminate();
            assert(false, 'strand was not terminated');
        })();
        assert(false, 'expected exception was not thrown');
    } catch (TerminatedException $e) {
        // ok ...
    }
});

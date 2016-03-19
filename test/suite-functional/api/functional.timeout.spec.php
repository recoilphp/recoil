<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Exception\TimeoutException;

rit('returns value if the coroutine returns before the timeout', function () {
    $result = yield Recoil::timeout(
        1,
        function () {
            return '<ok>';
            yield;
        }
    );

    expect($result)->to->equal('<ok>');
});

rit('propagates exception if the coroutine throws before the timeout', function () {
    try {
        yield Recoil::timeout(
            1,
            function () {
                throw new Exception('<exception>');
                yield;
            }
        );
        assert(false, 'Expected exception was not thrown.');
    } catch (Exception $e) {
        expect($e->getMessage())->to->equal('<exception>');
    }
});

rit('throws a timeout exception if the coroutine takes too long', function () {
    try {
        yield Recoil::timeout(
            0.05,
            function () {
                yield 0.1;
            }
        );
    } catch (TimeoutException $e) {
        // ok ...
    }
});

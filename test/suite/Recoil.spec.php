<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Recoil\Kernel\ApiCall;

describe(Recoil::class, function () {
    describe('::__callStatic()', function () {
        it('produces ApiCall instances representing the invocation', function () {
            expect(Recoil::foo(1, 2, 3))->to->loosely->equal(new ApiCall('foo', [1, 2, 3]));
        });
    });
});

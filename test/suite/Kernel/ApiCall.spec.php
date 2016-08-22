<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel;

describe(ApiCall::class, function () {
    it('represents the call', function () {
        $subject = new ApiCall('<name>', [1, 2, 3]);

        expect($subject->name, '<name>');
        expect($subject->arguments, [1, 2, 3]);
    });
});

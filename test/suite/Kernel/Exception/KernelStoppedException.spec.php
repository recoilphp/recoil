<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

describe(KernelStoppedException::class, function () {
    beforeEach(function () {
        $this->subject = new KernelStoppedException();
    });

    it('produces a useful message', function () {
        expect($this->subject->getMessage())->to->equal(
            'The kernel has been explicitly stopped.'
        );
    });
});

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Exception;

describe(KernelTrait::class, function () {

    xdescribe('->waitForStrand()', function () {
        it('runs the event loop', function () {
            $this->subject->wait();
            $this->eventLoop->run->called();
        });

        it('can be invoked again after an interrupt', function () {
            $this->eventLoop->run->does(function () {
                $this->subject->interrupt(new Exception());
            });

            expect(function () {
                $this->subject->wait();
            })->to->throw(Exception::class);

            expect(function () {
                $this->subject->wait();
            })->to->be->ok;
        });
    });

});

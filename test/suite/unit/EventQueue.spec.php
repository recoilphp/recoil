<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

use Eloquent\Phony\Phony;

describe(EventQueue::class, function () {
    beforeEach(function () {
        $this->subject = new EventQueue();
        $this->action1 = Phony::spy();
        $this->action2 = Phony::spy();
        $this->action3 = Phony::spy();
    });

    describe('->tick()', function () {
        it('executes events in order', function () {
            $this->subject->schedule(0.03, $this->action3);
            $this->subject->schedule(0.02, $this->action2);
            $this->subject->schedule(0.01, $this->action1);

            usleep(30000);
            $this->subject->tick();

            Phony::inOrder(
                $this->action1->called(),
                $this->action2->called(),
                $this->action3->called()
            );
        });

        it('does not execute future events', function () {
            $this->subject->schedule(5, $this->action1);

            $this->subject->tick();

            $this->action1->never()->called();
        });

        it('does not execute cancelled events', function () {
            $cancel = $this->subject->schedule(0, $this->action1);
            $cancel();

            $this->subject->tick();

            $this->action1->never()->called();
        });

        it('returns the number of microseconds until the next event', function () {
            $this->subject->schedule(5, $this->action1);

            $next = $this->subject->tick();

            expect($next)->to->be->within(5000000 - 50, 5000000 + 50);
        });

        it('returns null if there are no future events', function () {
            expect($this->subject->tick())->to->be->null;
        });
    });

    context('when an event is schedule inside an action', function () {
        it('does not invoke the new event until the next tick', function () {
            $this->subject->schedule(0, function () {
                $this->subject->schedule(0, $this->action1);
            });

            $this->subject->tick();

            $this->action1->never()->called();

            $this->subject->tick();

            $this->action1->called();
        });

        it('returns zero', function () {
            $this->subject->schedule(0, function () {
                $this->subject->schedule(0, function () {
                });
            });

            $next = $this->subject->tick();

            expect($next)->to->equal(0);
        });
    });
});

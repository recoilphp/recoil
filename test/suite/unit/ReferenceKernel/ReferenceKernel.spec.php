<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

use Eloquent\Phony\Phony;
use Recoil\Kernel\Api;

describe(EventQueue::class, function () {
    beforeEach(function () {
        $this->events = Phony::mock(EventQueue::class);
        $this->events->tick->returns(null); // i.e., no events

        $this->io = Phony::mock(IO::class);
        $this->io->tick->returns(IO::INACTIVE);

        $this->api = Phony::mock(Api::class);

        $this->subject = new ReferenceKernel(
            $this->events->get(),
            $this->io->get(),
            $this->api->get()
        );
    });

    describe('::create()', function () {
        it('returns a new kernel', function () {
            $subject = ReferenceKernel::create();

            $events = new EventQueue();
            $io = new IO();

            expect($subject)->to->loosely->equal(
                new ReferenceKernel(
                    $events,
                    $io,
                    new ReferenceApi($events, $io)
                )
            );
        });
    });

    describe('->execute()', function () {
        it('dispatches the coroutine on a future tick', function () {
            $strand = $this->subject->execute('<coroutine>');
            expect($strand)->to->be->an->instanceof(ReferenceStrand::class);

            $fn = $this->events->schedule->calledWith(0.0, '~')->firstCall()->argument(1);
            expect($fn)->to->satisfy('is_callable');

            $this->api->noInteraction();

            $fn();

            $this->api->__dispatch->calledWith(
                $strand,
                0,
                '<coroutine>'
            );
        });
    });

    describe('->loop()', function () {
        it('exits when there is nothing to do', function () {
            $time = microtime(true);
            $this->subject->run();
            $diff = microtime(true) - $time;

            expect($diff)->to->be->below(0.05);
        });

        it('ticks the event queue', function () {
            $this->subject->run();
            $this->events->tick->called();
        });

        it('ticks the IO system with the event queue timeout', function () {
            $this->events->tick->returns(123, null);

            $this->subject->run();

            $this->io->tick->calledWith(123);
        });

        it('does not tick IO if kernel is stopped by an event', function () {
            $this->events->tick->does(function () {
                $this->subject->stop();
            });

            $this->subject->run();

            $this->io->tick->never()->called();
        });

        it('does not tick events again if kernel is stopped by an IO handler', function () {
            $this->io->tick->does(function () {
                $this->subject->stop();

                return IO::INACTIVE;
            });

            $this->subject->run();

            $this->events->tick->once()->called();
        });
    });
});

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use Eloquent\Phony\Phony;
use Hamcrest\Core\IsInstanceOf;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Recoil\Kernel\Kernel;
use Recoil\Kernel\Strand;

describe(ReactApi::class, function () {

    beforeEach(function () {
        $this->eventLoop = Phony::mock(LoopInterface::class);
        $this->timer = Phony::mock(TimerInterface::class);
        $this->eventLoop->addTimer->returns($this->timer->mock());

        $this->kernel = Phony::mock(Kernel::class);

        $this->strand = Phony::mock(Strand::class);
        $this->strand->kernel->returns($this->kernel);

        $this->substrand = Phony::mock(Strand::class);
        $this->kernel->execute->returns($this->substrand);

        $this->subject = new ReactApi($this->eventLoop->mock());
    });

    describe('->cooperate()', function () {
        it('resumes the strand on a future tick', function () {
            $this->subject->cooperate(
                $this->strand->mock()
            );

            $fn = $this->eventLoop->futureTick->calledWith('~')->argument();
            expect($fn)->to->satisfy('is_callable');

            $this->strand->noInteraction();

            $fn();

            $this->strand->resume->calledWith();
        });
    });

    describe('->sleep()', function () {
        it('resumes the strand with a timer', function () {
            $this->subject->sleep(
                $this->strand->mock(),
                10.5
            );

            $fn = $this->eventLoop->addTimer->calledWith(10.5, '~')->argument(1);
            expect($fn)->to->satisfy('is_callable');

            $this->strand->resume->never()->called();

            $fn();

            $this->strand->resume->calledWith();
        });

        it('cancels the timer if the strand is terminated', function () {
            $this->subject->sleep(
                $this->strand->mock(),
                10.5
            );

            $cancel = $this->strand->setTerminator->called()->argument();
            expect($cancel)->to->satisfy('is_callable');

            $this->timer->cancel->never()->called();
            $cancel();
            $this->timer->cancel->called();
        });

        it('uses future tick instead of a timer when passed zero seconds', function () {
            $this->subject->sleep(
                $this->strand->mock(),
                0
            );

            $fn = $this->eventLoop->futureTick->calledWith('~')->argument();
            expect($fn)->to->satisfy('is_callable');

            $this->strand->noInteraction();

            $fn();

            $this->strand->resume->calledWith();
            $this->eventLoop->addTimer->never()->called();
        });

        it('uses future tick instead of a timer when passed negative seconds', function () {
            $this->subject->sleep(
                $this->strand->mock(),
                -1
            );

            $fn = $this->eventLoop->futureTick->calledWith('~')->argument();
            expect($fn)->to->satisfy('is_callable');

            $this->strand->noInteraction();

            $fn();

            $this->strand->resume->calledWith();
            $this->eventLoop->addTimer->never()->called();
        });
    });

    describe('->timeout()', function () {
        it('attaches a StrandTimeout instance to the substrand', function () {
            $this->subject->timeout(
                $this->strand->mock(),
                10.5,
                '<coroutine>'
            );

            $this->kernel->execute->calledWith('<coroutine>');

            $this->substrand->setObserver->calledWith(
                IsInstanceOf::anInstanceOf(StrandTimeout::class)
            );
        });
    });

    describe('->read()', function () {
        beforeEach(function () {
            $this->resource = fopen('php://memory', 'r+');
            fwrite($this->resource, '<buffer>');
            fseek($this->resource, 0);
        });

        it('resumes the strand with data read from the stream', function () {
            $this->subject->read(
                $this->strand->mock(),
                $this->resource
            );

            $fn = $this->eventLoop->addReadStream->calledWith($this->resource, '~')->argument(1);
            expect($fn)->to->satisfy('is_callable');

            $this->strand->resume->never()->called();

            $fn();

            $this->strand->resume->calledWith('<buffer>');
        });

        it('removes the stream from the event loop when data is received', function () {
            $this->eventLoop->addReadStream->callsArgument(1);

            $this->subject->read(
                $this->strand->mock(),
                $this->resource
            );

            Phony::inOrder(
                $this->eventLoop->removeReadStream->calledWith($this->resource),
                $this->strand->resume->called()
            );
        });

        it('removes the stream from the event loop when the strand is terminated', function () {
            $this->subject->read(
                $this->strand->mock(),
                $this->resource
            );

            $fn = $this->strand->setTerminator->calledWith('~')->argument();
            expect($fn)->to->satisfy('is_callable');

            $fn();

            $this->eventLoop->removeReadStream->calledWith($this->resource);
        });

        it('limits buffer size to the specified length', function () {
            $this->eventLoop->addReadStream->callsArgument(1);

            $this->subject->read(
                $this->strand->mock(),
                $this->resource,
                4
            );

            $this->strand->resume->calledWith('<buf');
        });
    });

    describe('->write()', function () {
        beforeEach(function () {
            $this->resource = fopen('php://memory', 'r+');
        });

        it('resumes the strand with the number of bytes written to the stream', function () {
            $this->subject->write(
                $this->strand->mock(),
                $this->resource,
                '<buffer>'
            );

            $fn = $this->eventLoop->addWriteStream->calledWith($this->resource, '~')->argument(1);
            expect($fn)->to->satisfy('is_callable');

            $this->strand->resume->never()->called();

            $fn();

            $this->strand->resume->calledWith(8);

            fseek($this->resource, 0);
            expect(fread($this->resource, 1024))->to->equal('<buffer>');
        });

        it('removes the stream from the event loop when data is sent', function () {
            $this->eventLoop->addWriteStream->callsArgument(1);

            $this->subject->write(
                $this->strand->mock(),
                $this->resource,
                '<buffer>'
            );

            Phony::inOrder(
                $this->eventLoop->removeWriteStream->calledWith($this->resource),
                $this->strand->resume->called()
            );
        });

        it('removes the stream from the event loop when the strand is terminated', function () {
            $this->subject->write(
                $this->strand->mock(),
                $this->resource,
                '<buffer>'
            );

            $fn = $this->strand->setTerminator->calledWith('~')->argument();
            expect($fn)->to->satisfy('is_callable');

            $fn();

            $this->eventLoop->removeWriteStream->calledWith($this->resource);
        });

        it('limits buffer size to the specified length', function () {
            $this->eventLoop->addWriteStream->callsArgument(1);

            $this->subject->write(
                $this->strand->mock(),
                $this->resource,
                '<buffer>',
                4
            );

            $this->strand->resume->calledWith(4);

            fseek($this->resource, 0);
            expect(fread($this->resource, 1024))->to->equal('<buf');
        });
    });

    describe('->eventLoop()', function () {
        it('resumes the strand with the internal event loop', function () {
            $this->subject->eventLoop(
                $this->strand->mock()
            );

            $this->strand->resume->calledWith($this->eventLoop);
        });
    });

});

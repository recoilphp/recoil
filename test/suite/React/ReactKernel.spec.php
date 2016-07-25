<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use Eloquent\Phony\Phony;
use Exception;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Recoil\Kernel\Api;
use Recoil\Recoil;
use Throwable;

describe(ReactKernel::class, function () {

    beforeEach(function () {
        $this->eventLoop = Phony::mock(LoopInterface::class);
        $this->api = Phony::mock(Api::class);

        $this->subject = new ReactKernel(
            $this->eventLoop->get(),
            $this->api->get()
        );
    });

    describe('::start()', function () {
        it('returns the coroutine result', function () {
            $result = ReactKernel::start(function () {
                return yield Recoil::eventLoop();
            });

            expect($result)->to->be->an->instanceof(LoopInterface::class);
        });

        it('propagates uncaught exceptions', function () {
            try {
                ReactKernel::start(function () {
                    throw new Exception('<exception>');
                    yield;
                });
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (Exception $e) {
                expect($e->getMessage())->to->equal('<exception>');
            }
        });

        it('uses the given event loop', function () {
            $eventLoop = Factory::create();

            $result = ReactKernel::start(
                function () {
                    return yield Recoil::eventLoop();
                },
                $eventLoop
            );

            expect($result)->to->equal($eventLoop);
        });
    });

    describe('->execute()', function () {
        it('dispatches the coroutine on a future tick', function () {
            $strand = $this->subject->execute('<coroutine>');
            expect($strand)->to->be->an->instanceof(ReactStrand::class);

            $fn = $this->eventLoop->futureTick->calledWith('~')->firstCall()->argument();
            expect($fn)->to->satisfy('is_callable');

            $this->api->noInteraction();

            $fn();

            $this->api->dispatch->calledWith(
                $strand,
                0,
                '<coroutine>'
            );
        });
    });

    describe('->wait()', function () {
        it('runs the event loop', function () {
            $this->subject->wait();
            $this->eventLoop->run->called();
        });
    });

    describe('->stop()', function () {
        it('stops the event loop', function () {
            $this->subject->stop();
            $this->eventLoop->stop->called();
        });

        it('causes wait() to return', function () {
            $exception = Phony::mock(Throwable::class)->get();
            $this->eventLoop->run->does(function () use ($exception) {
                $this->subject->stop();
            });

            expect(function () {
                $this->subject->wait();
            })->to->be->ok;
        });
    });

});

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use Eloquent\Phony\Phony;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Recoil\Exception\TimeoutException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Strand;
use Throwable;

describe(StrandTimeout::class, function () {

    beforeEach(function () {
        $this->api = Phony::mock(Api::class);
        $this->timer = Phony::mock(TimerInterface::class);
        $this->loop = Phony::mock(LoopInterface::class);
        $this->loop->addTimer->returns($this->timer->mock());
        $this->strand = Phony::mock(Strand::class);

        $this->substrand = Phony::mock(Strand::class);
        $this->substrand->id->returns(1);

        $this->subject = new StrandTimeout(
            $this->loop->mock(),
            20.5,
            $this->substrand->mock()
        );

        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );
    });

    describe('->await()', function () {
        it('attaches a timer with the correct timeout', function () {
            $this->loop->addTimer->calledWith(
                20.5,
                [$this->subject, 'timeout']
            );
        });

        it('resumes the strand when the substrand is successful', function () {
            $this->strand->setTerminator->calledWith([$this->subject, 'cancel']);
            $this->substrand->setPrimaryListener->calledWith($this->subject);

            $this->strand->send->never()->called();
            $this->strand->throw->never()->called();

            $this->subject->send('<ok>', $this->substrand->mock());

            $this->timer->cancel->called();
            $this->strand->send->calledWith('<ok>');
        });

        it('resumes the strand with an exception when the substrand fails', function () {
            $exception = Phony::mock(Throwable::class);
            $this->subject->throw($exception->mock(), $this->substrand->mock());

            $this->timer->cancel->called();
            $this->strand->throw->calledWith($exception);
        });

        it('resumes the strand with an exception if substrand times out', function () {
            $this->subject->timeout();

            $this->strand->throw->calledWith(
                new TimeoutException(20.5)
            );
        });
    });

    describe('->cancel()', function () {
        it('terminates the substrand', function () {
            $this->subject->cancel();

            Phony::inOrder(
                $this->substrand->setPrimaryListener->calledWith(null),
                $this->substrand->terminate->called()
            );
        });

        it('doesn\'t terminate the substrand if it has exited', function () {
            $this->subject->send('<ok>', $this->substrand->mock());
            $this->subject->cancel();

            $this->substrand->setPrimaryListener->never()->calledWith(null);
            $this->substrand->terminate->never()->called();
        });
    });

});

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Eloquent\Phony\Phony;
use Recoil\Exception\CompositeException;
use Throwable;

describe(StrandWaitAny::class, function () {

    beforeEach(function () {
        $this->api = Phony::mock(Api::class);

        $this->strand = Phony::mock(Strand::class);

        $this->substrand1 = Phony::mock(Strand::class);
        $this->substrand1->id->returns(1);

        $this->substrand2 = Phony::mock(Strand::class);
        $this->substrand2->id->returns(2);

        $this->subject = new StrandWaitAny(
            $this->substrand1->mock(),
            $this->substrand2->mock()
        );

        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );
    });

    describe('->await()', function () {
        it('resumes the strand when any substrand succeeds', function () {
            $this->strand->setTerminator->calledWith([$this->subject, 'cancel']);
            $this->substrand1->setPrimaryListener->calledWith($this->subject);
            $this->substrand2->setPrimaryListener->calledWith($this->subject);

            $this->subject->send('<one>', $this->substrand1->mock());

            $this->strand->send->calledWith('<one>');
        });

        it('resumes the strand with an exception when all substrands fail', function () {
            $exception2 = Phony::mock(Throwable::class);
            $this->subject->throw($exception2->mock(), $this->substrand2->mock());

            $this->strand->send->never()->called();
            $this->strand->throw->never()->called();

            $exception1 = Phony::mock(Throwable::class);
            $this->subject->throw($exception1->mock(), $this->substrand1->mock());

            $this->strand->throw->calledWith(
                new CompositeException(
                    [
                        1 => $exception2->mock(),
                        0 => $exception1->mock(),
                    ]
                )
            );
        });

        it('terminates unused substrands', function () {
            $this->subject->send('<one>', $this->substrand1->mock());

            Phony::inOrder(
                $this->substrand2->setPrimaryListener->calledWith(null),
                $this->substrand2->terminate->called(),
                $this->strand->send->called()
            );
        });
    });

    describe('->cancel()', function () {
        it('only terminates the remaining substrands', function () {
            $exception = Phony::mock(Throwable::class);
            $this->subject->throw($exception->mock(), $this->substrand1->mock());

            $this->subject->cancel();

            $this->substrand1->setPrimaryListener->never()->calledWith(null);
            $this->substrand1->terminate->never()->called();

            Phony::inOrder(
                $this->substrand2->setPrimaryListener->calledWith(null),
                $this->substrand2->terminate->called()
            );
        });
    });

});

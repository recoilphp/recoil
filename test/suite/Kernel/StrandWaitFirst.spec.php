<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Eloquent\Phony\Phony;
use Throwable;

describe(StrandWaitFirst::class, function () {

    beforeEach(function () {
        $this->api = Phony::mock(Api::class);

        $this->strand = Phony::mock(Strand::class);

        $this->substrand1 = Phony::mock(Strand::class);
        $this->substrand1->id->returns(1);

        $this->substrand2 = Phony::mock(Strand::class);
        $this->substrand2->id->returns(2);

        $this->subject = new StrandWaitFirst(
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

        it('resumes the strand with an exception when any substrand fails', function () {
            $exception = Phony::mock(Throwable::class);
            $this->subject->throw($exception->mock(), $this->substrand1->mock());

            Phony::inOrder(
                $this->substrand2->clearPrimaryListener->called(),
                $this->substrand2->terminate->called(),
                $this->strand->throw->calledWith($exception)
            );
        });

        it('terminates unused substrands', function () {
            $this->subject->send('<one>', $this->substrand1->mock());

            Phony::inOrder(
                $this->substrand2->clearPrimaryListener->called(),
                $this->substrand2->terminate->called(),
                $this->strand->send->called()
            );
        });
    });

    describe('->cancel()', function () {
        it('terminates the remaining substrands', function () {
            $this->subject->cancel();

            Phony::inOrder(
                $this->substrand1->clearPrimaryListener->called(),
                $this->substrand1->terminate->called()
            );

            Phony::inOrder(
                $this->substrand2->clearPrimaryListener->called(),
                $this->substrand2->terminate->called()
            );
        });

        it('does not terminate strands twice', function () {
            $this->subject->send('<one>', $this->substrand1->mock());

            $this->subject->cancel();

            $this->substrand1->clearPrimaryListener->never()->called();
            $this->substrand1->terminate->never()->called();

            $this->substrand2->clearPrimaryListener->once()->called();
            $this->substrand2->terminate->once()->called();
        });
    });

});

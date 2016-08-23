<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Eloquent\Phony\Phony;
use Recoil\Exception\CompositeException;
use Throwable;

describe(StrandWaitAny::class, function () {
    beforeEach(function () {
        $this->api = Phony::mock(Api::class);

        $this->strand = Phony::mock(SystemStrand::class);

        $this->substrand1 = Phony::mock(SystemStrand::class);
        $this->substrand1->id->returns(1);

        $this->substrand2 = Phony::mock(SystemStrand::class);
        $this->substrand2->id->returns(2);

        $this->subject = new StrandWaitAny(
            $this->substrand1->get(),
            $this->substrand2->get()
        );

        $this->subject->await(
            $this->strand->get(),
            $this->api->get()
        );
    });

    describe('->await()', function () {
        it('resumes the strand when any substrand succeeds', function () {
            $this->strand->setTerminator->calledWith([$this->subject, 'cancel']);
            $this->substrand1->setPrimaryListener->calledWith($this->subject);
            $this->substrand2->setPrimaryListener->calledWith($this->subject);

            $this->subject->send('<one>', $this->substrand1->get());

            $this->strand->send->calledWith('<one>', $this->substrand1->get());
        });

        it('resumes the strand with an exception when all substrands fail', function () {
            $exception2 = Phony::mock(Throwable::class);
            $this->subject->throw($exception2->get(), $this->substrand2->get());

            $this->strand->send->never()->called();
            $this->strand->throw->never()->called();

            $exception1 = Phony::mock(Throwable::class);
            $this->subject->throw($exception1->get(), $this->substrand1->get());

            $this->strand->throw->calledWith(
                CompositeException::create(
                    [
                        1 => $exception2->get(),
                        0 => $exception1->get(),
                    ]
                )
            );
        });

        it('terminates unused substrands', function () {
            $this->subject->send('<one>', $this->substrand1->get());

            Phony::inOrder(
                $this->substrand2->clearPrimaryListener->called(),
                $this->substrand2->terminate->called(),
                $this->strand->send->called()
            );
        });
    });

    describe('->cancel()', function () {
        it('only terminates the remaining substrands', function () {
            $exception = Phony::mock(Throwable::class);
            $this->subject->throw($exception->get(), $this->substrand1->get());

            $this->subject->cancel();

            $this->substrand1->setPrimaryListener->never()->calledWith(null);
            $this->substrand1->terminate->never()->called();

            Phony::inOrder(
                $this->substrand2->clearPrimaryListener->called(),
                $this->substrand2->terminate->called()
            );
        });
    });
});

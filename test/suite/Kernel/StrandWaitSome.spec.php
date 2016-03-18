<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Eloquent\Phony\Phony;
use Recoil\Exception\CompositeException;
use Recoil\Exception\TerminatedException;
use Throwable;

describe(StrandWaitSome::class, function () {

    beforeEach(function () {
        $this->api = Phony::mock(Api::class);

        $this->strand = Phony::mock(Strand::class);

        $this->substrand1 = Phony::mock(Strand::class);
        $this->substrand1->id->returns(1);

        $this->substrand2 = Phony::mock(Strand::class);
        $this->substrand2->id->returns(2);

        $this->substrand3 = Phony::mock(Strand::class);
        $this->substrand3->id->returns(3);

        $this->subject = new StrandWaitSome(
            2,
            $this->substrand1->mock(),
            $this->substrand2->mock(),
            $this->substrand3->mock()
        );

        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );
    });

    describe('->count()', function () {

        it('only counts the pending strands', function () {
            expect($this->subject->count())->to->equal(2);
            $this->subject->success($this->substrand3->mock(), '<three>');
            expect($this->subject->count())->to->equal(1);
        });

    });

    describe('->await()', function () {
        it('resumes the strand when enough substrands complete', function () {
            $this->substrand1->setObserver->calledWith($this->subject);
            $this->substrand2->setObserver->calledWith($this->subject);
            $this->substrand3->setObserver->calledWith($this->subject);

            $this->subject->success($this->substrand2->mock(), '<two>');

            $this->strand->resume->never()->called();
            $this->strand->throw->never()->called();

            $this->subject->success($this->substrand1->mock(), '<one>');

            $this->strand->resume->calledWith(
                [
                    1 => '<two>',
                    0 => '<one>',
                ]
            );
        });

        it('resumes the strand with an exception when too many substrands fail', function () {
            $exception2 = Phony::mock(Throwable::class);
            $this->subject->failure($this->substrand2->mock(), $exception2->mock());

            $this->strand->resume->never()->called();
            $this->strand->throw->never()->called();

            $exception1 = Phony::mock(Throwable::class);
            $this->subject->failure($this->substrand1->mock(), $exception1->mock());

            Phony::inOrder(
                $this->substrand3->setObserver->calledWith(null),
                $this->substrand3->terminate->called(),
                $this->strand->throw->calledWith(
                    new CompositeException(
                        [
                            1 => $exception2->mock(),
                            0 => $exception1->mock(),
                        ]
                    )
                )
            );
        });

        it('resumes the strand with an exception when too many substrands are terminated', function () {
            $this->subject->terminated($this->substrand2->mock());
            $this->subject->terminated($this->substrand1->mock());

            Phony::inOrder(
                $this->substrand3->setObserver->calledWith(null),
                $this->substrand3->terminate->called(),
                $this->strand->throw->calledWith(
                    new CompositeException(
                        [
                            1 => new TerminatedException($this->substrand2->mock()),
                            0 => new TerminatedException($this->substrand1->mock()),
                        ]
                    )
                )
            );
        });

        it('terminates unused substrands', function () {
            $this->strand->setTerminator->calledWith([$this->subject, 'cancel']);

            $this->subject->success($this->substrand2->mock(), '<two>');
            $this->subject->success($this->substrand1->mock(), '<one>');

            Phony::inOrder(
                $this->substrand3->setObserver->calledWith(null),
                $this->substrand3->terminate->called(),
                $this->strand->resume->called()
            );
        });
    });

    describe('->cancel()', function () {
        it('only terminates the pending substrands', function () {
            $this->subject->success($this->substrand1->mock(), '<one>');

            $this->subject->cancel();

            $this->substrand1->setObserver->never()->calledWith(null);
            $this->substrand1->terminate->never()->called();

            Phony::inOrder(
                $this->substrand2->setObserver->calledWith(null),
                $this->substrand2->terminate->called()
            );

            Phony::inOrder(
                $this->substrand3->setObserver->calledWith(null),
                $this->substrand3->terminate->called()
            );
        });
    });

});

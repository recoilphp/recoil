<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Eloquent\Phony\Phony;
use Recoil\Exception\TerminatedException;
use Throwable;

describe(StrandWaitOne::class, function () {

    beforeEach(function () {
        $this->api = Phony::mock(Api::class);

        $this->strand = Phony::mock(Strand::class);

        $this->substrand = Phony::mock(Strand::class);
        $this->substrand->id->returns(1);

        $this->subject = new StrandWaitOne(
            $this->substrand->mock()
        );

        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );
    });

    describe('->await()', function () {
        it('resumes the strand when the substrand succeeds', function () {
            $this->strand->setTerminator->calledWith([$this->subject, 'cancel']);
            $this->substrand->setObserver->calledWith($this->subject);

            $this->subject->success($this->substrand->mock(), '<one>');

            $this->strand->resume->calledWith('<one>');
        });

        it('resumes the strand with an exception when the substrand fails', function () {
            $exception = Phony::mock(Throwable::class);
            $this->subject->failure($this->substrand->mock(), $exception->mock());

            $this->strand->throw->calledWith($exception);
        });

        it('resumes the strand with an exception when the substrand is terminated', function () {
            $this->subject->terminated($this->substrand->mock());

            $this->strand->throw->calledWith(
                new TerminatedException($this->substrand->mock())
            );
        });
    });

    describe('->cancel()', function () {
        it('terminates the substrand', function () {
            $this->subject->cancel();

            Phony::inOrder(
                $this->substrand->setObserver->calledWith(null),
                $this->substrand->terminate->called()
            );
        });

        it('does not terminate the substrand if it has already exited', function () {
            $this->subject->success($this->substrand->mock(), '<one>');

            $this->subject->cancel();

            $this->substrand->setObserver->never()->calledWith(null);
            $this->substrand->terminate->never()->called();
        });
    });

});

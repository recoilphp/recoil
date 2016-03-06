<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Eloquent\Phony\Phpunit\Phony;
use PHPUnit_Framework_TestCase;
use Recoil\Exception\CompositeException;
use Recoil\Exception\TerminatedException;
use Throwable;

class StrandWaitSomeTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
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
    }

    public function testCount()
    {
        $this->assertSame(
            2,
            $this->subject->count()
        );

        $this->subject->success($this->substrand3->mock(), '<three>');

        $this->assertSame(
            1,
            $this->subject->count()
        );
    }

    public function testAwait()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );

        $this->strand->setTerminator->calledWith([$this->subject, 'cancel']);
        $this->substrand1->attachObserver->calledWith($this->subject);
        $this->substrand2->attachObserver->calledWith($this->subject);
        $this->substrand3->attachObserver->calledWith($this->subject);

        $this->subject->success($this->substrand2->mock(), '<two>');

        $this->strand->resume->never()->called();
        $this->strand->throw->never()->called();

        $this->subject->success($this->substrand1->mock(), '<one>');

        Phony::inOrder(
            $this->substrand3->detachObserver->calledWith($this->subject),
            $this->substrand3->terminate->called(),
            $this->strand->resume->calledWith(
                [
                    1 => '<two>',
                    0 => '<one>',
                ]
            )
        );
    }

    public function testAwaitWithFailedStrands()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );

        $exception2 = Phony::mock(Throwable::class);
        $this->subject->failure($this->substrand2->mock(), $exception2->mock());

        $this->strand->resume->never()->called();
        $this->strand->throw->never()->called();

        $exception1 = Phony::mock(Throwable::class);
        $this->subject->failure($this->substrand1->mock(), $exception1->mock());

        Phony::inOrder(
            $this->substrand3->detachObserver->calledWith($this->subject),
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
    }

    public function testAwaitWithTerminatedStrands()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );

        $this->subject->terminated($this->substrand2->mock());
        $this->subject->terminated($this->substrand1->mock());

        Phony::inOrder(
            $this->substrand3->detachObserver->calledWith($this->subject),
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
    }

    public function testCancel()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );

        $this->subject->cancel();

        Phony::inOrder(
            $this->substrand1->detachObserver->calledWith($this->subject),
            $this->substrand1->terminate->called()
        );

        Phony::inOrder(
            $this->substrand2->detachObserver->calledWith($this->subject),
            $this->substrand2->terminate->called()
        );

        Phony::inOrder(
            $this->substrand3->detachObserver->calledWith($this->subject),
            $this->substrand3->terminate->called()
        );
    }
}

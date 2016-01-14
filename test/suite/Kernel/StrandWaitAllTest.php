<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Eloquent\Phony\Phpunit\Phony;
use Exception;
use Generator;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Recoil\Exception\TerminatedException;
use Throwable;

class StrandWaitAllTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->api = Phony::mock(Api::class);

        $this->strand = Phony::mock(Strand::class);

        $this->substrand1 = Phony::mock(Strand::class);
        $this->substrand1->id->returns(1);

        $this->substrand2 = Phony::mock(Strand::class);
        $this->substrand2->id->returns(2);

        $this->subject = new StrandWaitAll(
            $this->substrand1->mock(),
            $this->substrand2->mock()
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

        $this->subject->success($this->substrand1->mock(), '<one>');

        $this->strand->resume->never()->called();
        $this->strand->throw->never()->called();

        $this->subject->success($this->substrand2->mock(), '<two>');

        $this->strand->resume->calledWith(['<one>', '<two>']);
    }

    public function testAwaitWithFailedStrand()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );

        $exception = Phony::mock(Throwable::class);
        $this->subject->failure($this->substrand1->mock(), $exception->mock());

        Phony::inOrder(
            $this->substrand2->detachObserver->calledWith($this->subject),
            $this->substrand2->terminate->called(),
            $this->strand->throw->calledWith($exception->mock())
        );
    }

    public function testAwaitWithTerminatedStrand()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );

        $exception = Phony::mock(Throwable::class);
        $this->subject->terminated($this->substrand1->mock(), $exception->mock());

        Phony::inOrder(
            $this->substrand2->detachObserver->calledWith($this->subject),
            $this->substrand2->terminate->called(),
            $this->strand->throw->calledWith(
                new TerminatedException($this->substrand1->mock())
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
    }
}

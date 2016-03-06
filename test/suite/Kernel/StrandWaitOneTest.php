<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Eloquent\Phony\Phpunit\Phony;
use PHPUnit_Framework_TestCase;
use Recoil\Exception\TerminatedException;
use Throwable;

class StrandWaitOneTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->api = Phony::mock(Api::class);

        $this->strand = Phony::mock(Strand::class);

        $this->substrand = Phony::mock(Strand::class);
        $this->substrand->id->returns(1);

        $this->subject = new StrandWaitOne(
            $this->substrand->mock()
        );
    }

    public function testAwait()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );

        $this->strand->setTerminator->calledWith([$this->subject, 'cancel']);
        $this->substrand->attachObserver->calledWith($this->subject);

        $this->subject->success($this->substrand->mock(), '<one>');

        $this->strand->resume->calledWith('<one>');
    }

    public function testAwaitWithFailedStrands()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );

        $exception = Phony::mock(Throwable::class);
        $this->subject->failure($this->substrand->mock(), $exception->mock());

        $this->strand->throw->calledWith($exception->mock());
    }

    public function testAwaitWithTerminatedStrands()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );

        $this->subject->terminated($this->substrand->mock());

        $this->strand->throw->calledWith(
            new TerminatedException($this->substrand->mock())
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
            $this->substrand->detachObserver->calledWith($this->subject),
            $this->substrand->terminate->called()
        );
    }
}

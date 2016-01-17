<?php

declare (strict_types = 1);

namespace Recoil\React;

use Eloquent\Phony\Phpunit\Phony;
use PHPUnit_Framework_TestCase;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Recoil\Kernel\Awaitable;
use Recoil\Kernel\Kernel;
use Recoil\Kernel\Strand;

class ReactApiTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->eventLoop = Phony::mock(LoopInterface::class);
        $this->timer = Phony::mock(TimerInterface::class);
        $this->eventLoop->addTimer->returns($this->timer->mock());

        $this->kernel = Phony::mock(Kernel::class);

        $this->strand = Phony::mock(Strand::class);
        $this->strand->kernel->returns($this->kernel->mock());

        $this->substrand = Phony::mock(Strand::class);
        $this->kernel->execute->returns($this->substrand->mock());

        $this->subject = new ReactApi($this->eventLoop->mock());
    }

    public function testExecute()
    {
        $this->subject->execute(
            $this->strand->mock(),
            '<task>'
        );

        $this->kernel->execute->calledWith('<task>');
        $this->strand->resume->calledWith($this->substrand->mock());
    }

    public function testCallback()
    {
        $this->subject->callback(
            $this->strand->mock(),
            '<task>'
        );

        $fn = $this->strand->resume->calledWith('~')->argument();
        $this->assertTrue(is_callable($fn));

        $this->kernel->execute->never()->called();

        $fn();

        $this->kernel->execute->calledWith('<task>');
    }

    public function testCooperate()
    {
        $this->subject->cooperate(
            $this->strand->mock()
        );

        $fn = $this->eventLoop->futureTick->calledWith('~')->argument();
        $this->assertTrue(is_callable($fn));

        $this->strand->noInteraction();

        $fn();

        $this->strand->resume->calledWith();
    }

    public function testSleep()
    {
        $this->subject->sleep(
            $this->strand->mock(),
            10.5
        );

        $fn = $this->eventLoop->addTimer->calledWith(10.5, '~')->argument(1);
        $this->assertTrue(is_callable($fn));

        $this->strand->setTerminator->calledWith(
            [$this->timer->mock(), 'cancel']
        );

        $this->strand->resume->never()->called();

        $fn();

        $this->strand->resume->calledWith();
    }

    public function testSleepWithZeroSeconds()
    {
        $this->subject->sleep(
            $this->strand->mock(),
            0
        );

        $fn = $this->eventLoop->futureTick->calledWith('~')->argument();
        $this->assertTrue(is_callable($fn));

        $this->strand->noInteraction();

        $fn();

        $this->strand->resume->calledWith();
    }

    public function testSleepWithNegativeSeconds()
    {
        $this->subject->sleep(
            $this->strand->mock(),
            -1
        );

        $fn = $this->eventLoop->futureTick->calledWith('~')->argument();
        $this->assertTrue(is_callable($fn));

        $this->strand->noInteraction();

        $fn();

        $this->strand->resume->calledWith();
    }

    public function testTimeout()
    {
        $this->markTestIncomplete('Requires strands to be awaitable.');

        $this->subject->timeout(
            $this->strand->mock(),
            10.5,
            '<task>'
        );

        $fn = $this->eventLoop->addTimer->calledWith(10.5, '~')->argument(1);
        $this->assertTrue(is_callable($fn));

        $this->substrand->noInteraction();

        $fn();

        $this->substrand->terminate->called();
    }

    public function testEventLoop()
    {
        $this->subject->eventLoop(
            $this->strand->mock()
        );

        $this->strand->resume->calledWith($this->eventLoop->mock());
    }
}

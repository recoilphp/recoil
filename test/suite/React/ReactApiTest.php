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

        $cancel = $this->strand->setTerminator->called()->argument();
        $this->assertTrue(is_callable($cancel));

        $this->strand->resume->never()->called();

        $fn();

        $this->strand->resume->calledWith();

        $this->eventLoop->futureTick->never()->called();

        $this->timer->cancel->never()->called();
        $cancel();
        $this->timer->cancel->called();
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

        $this->eventLoop->addTimer->never()->called();
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

        $this->eventLoop->addTimer->never()->called();
    }

    public function testTimeout()
    {
        $awaitable = Phony::mock(Awaitable::class);
        $this->substrand->awaitable->returns($awaitable->mock());

        $this->subject->timeout(
            $this->strand->mock(),
            10.5,
            '<task>'
        );

        $fn = $this->eventLoop->addTimer->calledWith(10.5, '~')->argument(1);
        $this->assertTrue(is_callable($fn));

        $awaitable->await->calledWith(
            $this->strand->mock(),
            $this->subject
        );

        $this->substrand->setTerminator->calledWith(
            [$this->timer->mock(), 'cancel']
        );

        $this->substrand->terminate->never()->called();

        $fn();

        $this->substrand->terminate->called();
    }

    public function testRead()
    {
        $fp = fopen('php://memory', 'r+');
        fwrite($fp, '<buffer>');
        fseek($fp, 0);

        $this->subject->read(
            $this->strand->mock(),
            $fp
        );

        $fn = $this->eventLoop->addReadStream->calledWith($fp, '~')->argument(1);
        $this->assertTrue(is_callable($fn));

        $this->strand->resume->never()->called();
        $this->eventLoop->removeReadStream->never()->called();

        $fn();

        Phony::inOrder(
            $this->eventLoop->removeReadStream->calledWith($fp),
            $this->strand->resume->calledWith('<buffer>')
        );
    }

    public function testReadWithLength()
    {
        $fp = fopen('php://memory', 'r+');
        fwrite($fp, '<buffer>');
        fseek($fp, 0);

        $this->subject->read(
            $this->strand->mock(),
            $fp,
            4
        );

        $fn = $this->eventLoop->addReadStream->calledWith($fp, '~')->argument(1);
        $this->assertTrue(is_callable($fn));

        $this->strand->resume->never()->called();
        $this->eventLoop->removeReadStream->never()->called();

        $fn();

        Phony::inOrder(
            $this->eventLoop->removeReadStream->calledWith($fp),
            $this->strand->resume->calledWith('<buf')
        );
    }

    public function testReadTermination()
    {
        $fp = fopen('php://memory', 'r+');

        $this->subject->read(
            $this->strand->mock(),
            $fp
        );

        $fn = $this->strand->setTerminator->calledWith('~')->argument();
        $this->assertTrue(is_callable($fn));

        $fn();

        $this->eventLoop->removeReadStream->calledWith($fp);
    }

    public function testWrite()
    {
        $fp = fopen('php://memory', 'r+');

        $this->subject->write(
            $this->strand->mock(),
            $fp,
            '<buffer>'
        );

        $fn = $this->eventLoop->addWriteStream->calledWith($fp, '~')->argument(1);
        $this->assertTrue(is_callable($fn));

        $this->strand->resume->never()->called();
        $this->eventLoop->removeWriteStream->never()->called();

        $fn();

        Phony::inOrder(
            $this->eventLoop->removeWriteStream->calledWith($fp),
            $this->strand->resume->calledWith(8)
        );

        fseek($fp, 0);

        $this->assertSame(
            '<buffer>',
            fread($fp, 1024)
        );
    }

    public function testWriteTermination()
    {
        $fp = fopen('php://memory', 'w+');

        $this->subject->write(
            $this->strand->mock(),
            $fp,
            '<buffer>'
        );

        $fn = $this->strand->setTerminator->calledWith('~')->argument();
        $this->assertTrue(is_callable($fn));

        $fn();

        $this->eventLoop->removeWriteStream->calledWith($fp);
    }

    public function testEventLoop()
    {
        $this->subject->eventLoop(
            $this->strand->mock()
        );

        $this->strand->resume->calledWith($this->eventLoop->mock());
    }
}

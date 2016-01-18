<?php

declare (strict_types = 1);

namespace Recoil\React;

use Eloquent\Phony\Phpunit\Phony;
use Exception;
use PHPUnit_Framework_TestCase;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Recoil\Kernel\Api;
use Recoil\Recoil;
use RuntimeException;

class ReactKernelTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->eventLoop = Phony::mock(LoopInterface::class);
        $this->api = Phony::mock(Api::class);

        $this->subject = new ReactKernel(
            $this->eventLoop->mock(),
            $this->api->mock()
        );
    }

    public function testStart()
    {
        $result = ReactKernel::start(
            function () {
                return yield Recoil::eventLoop();
            }
        );

        $this->assertInstanceOf(
            LoopInterface::class,
            $result
        );
    }

    public function testStartWithFailure()
    {
        $this->setExpectedException(
            Exception::class,
            '<exception>'
        );

        ReactKernel::start(
            function () {
                throw new Exception('<exception>');
                yield;
            }
        );
    }

    public function testStartWithAbandonedCoroutine()
    {
        $this->setExpectedException(
            RuntimeException::class,
            'The coroutine did not complete.'
        );

        ReactKernel::start(
            function () {
                yield Recoil::suspend();
            }
        );
    }

    public function testStartWithEventLoop()
    {
        $eventLoop = Factory::create();

        $result = ReactKernel::start(
            function () {
                return yield Recoil::eventLoop();
            },
            $eventLoop
        );

        $this->assertSame(
            $eventLoop,
            $result
        );
    }

    public function testExecute()
    {
        $strand = $this->subject->execute('<task>');

        $fn = $this->eventLoop->futureTick->calledWith('~')->argument();

        $this->assertInstanceOf(
            ReactStrand::class,
            $strand
        );

        $this->assertTrue(is_callable($fn));

        $this->api->noInteraction();

        $fn();

        $this->api->__dispatch->calledWith(
            $strand,
            0,
            '<task>'
        );
    }
}

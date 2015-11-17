<?php

namespace Recoil\Kernel;

use PHPUnit_Framework_TestCase;
use React\EventLoop\LoopInterface;
use Recoil\Coroutine\StandardCoroutineAdaptor;
use Recoil\Kernel\Api\KernelApi;
use Recoil\Kernel\Strand\StrandFactory;
use Recoil\Recoil;

class KernelTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel();
    }

    public function testConstructorDefaults()
    {
        $this->assertInstanceOf(KernelApi::class, $this->kernel->api());
        $this->assertInstanceOf(StandardCoroutineAdaptor::class, $this->kernel->coroutineAdaptor());
        $this->assertInstanceOf(StrandFactory::class, $this->kernel->strandFactory());
        $this->assertInstanceOf(LoopInterface::class, $this->kernel->eventLoop());
    }

    public function testStop()
    {
        $this->expectOutputString('1');

        $coroutine = function () {
            yield Recoil::sleep(0.2);
            echo 'X';
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->addTimer(
            0.1,
            function () {
                echo 1;
                $this->kernel->stop();
            }
        );

        $this->kernel->eventLoop()->run();
    }
}

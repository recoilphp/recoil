<?php
namespace Icecave\Recoil\Kernel;

use Icecave\Recoil\Coroutine\CoroutineAdaptor;
use Icecave\Recoil\Kernel\Api\KernelApi;
use Icecave\Recoil\Kernel\Strand\StrandFactory;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;
use React\EventLoop\LoopInterface;

class KernelTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel;
    }

    public function testConstructorDefaults()
    {
        $this->assertInstanceOf(KernelApi::CLASS, $this->kernel->api());
        $this->assertInstanceOf(CoroutineAdaptor::CLASS, $this->kernel->coroutineAdaptor());
        $this->assertInstanceOf(StrandFactory::CLASS, $this->kernel->strandFactory());
        $this->assertInstanceOf(LoopInterface::CLASS, $this->kernel->eventLoop());
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

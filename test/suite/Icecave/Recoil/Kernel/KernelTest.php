<?php
namespace Icecave\Recoil\Kernel;

use Icecave\Recoil\Coroutine\CoroutineAdaptor;
use PHPUnit_Framework_TestCase;
use React\EventLoop\LoopInterface;

class KernelTest extends PHPUnit_Framework_TestCase
{
    public function testConstructorDefaults()
    {
        $kernel = new Kernel;

        $this->assertInstanceOf(KernelApiInterface::CLASS, $kernel->api());
        $this->assertInstanceOf(CoroutineAdaptor::CLASS, $kernel->coroutineAdaptor());
        $this->assertInstanceOf(StrandFactory::CLASS, $kernel->strandFactory());
        $this->assertInstanceOf(LoopInterface::CLASS, $kernel->eventLoop());
    }
}

<?php
namespace Icecave\Recoil\Kernel;

use Icecave\Recoil\Coroutine\CoroutineAdaptor;
use Icecave\Recoil\Kernel\Api\KernelApi;
use Icecave\Recoil\Kernel\ExceptionHandler\StrictExceptionHandler;
use Icecave\Recoil\Kernel\Strand\StrandFactory;
use PHPUnit_Framework_TestCase;
use React\EventLoop\LoopInterface;

class KernelTest extends PHPUnit_Framework_TestCase
{
    public function testConstructorDefaults()
    {
        $kernel = new Kernel;

        $this->assertInstanceOf(KernelApi::CLASS, $kernel->api());
        $this->assertInstanceOf(CoroutineAdaptor::CLASS, $kernel->coroutineAdaptor());
        $this->assertInstanceOf(StrandFactory::CLASS, $kernel->strandFactory());
        $this->assertInstanceOf(StrictExceptionHandler::CLASS, $kernel->exceptionHandler());
        $this->assertInstanceOf(LoopInterface::CLASS, $kernel->eventLoop());
    }
}

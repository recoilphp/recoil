<?php
namespace Icecave\Recoil\Channel;

use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;
use Phake;

class BidirectionalChannelAdaptorTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel  = new Kernel;
        $this->readChannel = Phake::mock(ReadableChannelInterface::CLASS);
        $this->writeChannel = Phake::mock(WritableChannelInterface::CLASS);
        $this->adaptor = new BidirectionalChannelAdaptor(
            $this->readChannel,
            $this->writeChannel
        );
    }

    public function testRead()
    {
        Phake::when($this->readChannel)
            ->read()
            ->thenReturn('<read coroutine>');

        $this->assertSame('<read coroutine>', $this->adaptor->read());
    }

    public function testWrite()
    {
        Phake::when($this->writeChannel)
            ->write(123)
            ->thenReturn('<write coroutine>');

        $this->assertSame('<write coroutine>', $this->adaptor->write(123));
    }

    public function testClose()
    {
        $this->kernel->execute($this->adaptor->close());

        $this->kernel->eventLoop()->run();

        Phake::verify($this->readChannel)->close();
        Phake::verify($this->writeChannel)->close();
    }

    public function testIsClosedWhenReadChannelClosed()
    {
        $this->assertFalse($this->adaptor->isClosed());

        Phake::when($this->readChannel)
            ->isClosed()
            ->thenReturn(true);

        $this->assertTrue($this->adaptor->isClosed());
    }

    public function testIsClosedWhenWriteChannelClosed()
    {
        $this->assertFalse($this->adaptor->isClosed());

        Phake::when($this->writeChannel)
            ->isClosed()
            ->thenReturn(true);

        $this->assertTrue($this->adaptor->isClosed());
    }
}

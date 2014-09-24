<?php
namespace Recoil\Stream;

use Recoil\Recoil;
use PHPUnit_Framework_TestCase;
use Phake;

class BidirectionalStreamAdaptorTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->readStream = Phake::mock(ReadableStreamInterface::class);
        $this->writeStream = Phake::mock(WritableStreamInterface::class);
        $this->adaptor = new BidirectionalStreamAdaptor(
            $this->readStream,
            $this->writeStream
        );
    }

    public function testRead()
    {
        Phake::when($this->readStream)
            ->read(Phake::anyParameters())
            ->thenReturn('<read coroutine>');

        $this->assertSame('<read coroutine>', $this->adaptor->read(123));

        Phake::verify($this->readStream)->read(123);
    }

    public function testWrite()
    {
        Phake::when($this->writeStream)
            ->write(Phake::anyParameters())
            ->thenReturn('<write coroutine>');

        $this->assertSame('<write coroutine>', $this->adaptor->write('foo bar', 123));

        Phake::verify($this->writeStream)->write('foo bar', 123);
    }

    public function testWriteAll()
    {
        Phake::when($this->writeStream)
            ->writeAll(Phake::anyParameters())
            ->thenReturn('<write coroutine>');

        $this->assertSame('<write coroutine>', $this->adaptor->writeAll('foo bar'));

        Phake::verify($this->writeStream)->writeAll('foo bar');
    }

    public function testClose()
    {
        Recoil::run(
            function () {
                yield $this->adaptor->close();
            }
        );

        Phake::verify($this->readStream)->close();
        Phake::verify($this->writeStream)->close();
    }

    public function testIsClosedWhenreadStreamClosed()
    {
        $this->assertFalse($this->adaptor->isClosed());

        Phake::when($this->readStream)
            ->isClosed()
            ->thenReturn(true);

        $this->assertTrue($this->adaptor->isClosed());
    }

    public function testIsClosedWhenWriteStreamClosed()
    {
        $this->assertFalse($this->adaptor->isClosed());

        Phake::when($this->writeStream)
            ->isClosed()
            ->thenReturn(true);

        $this->assertTrue($this->adaptor->isClosed());
    }
}

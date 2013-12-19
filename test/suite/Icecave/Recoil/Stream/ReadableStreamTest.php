<?php
namespace Icecave\Recoil\Stream;

use Exception;
use Icecave\Recoil\Recoil;
use Icecave\Recoil\Stream\Exception\StreamClosedException;
use Icecave\Recoil\Stream\Exception\StreamLockedException;
use PHPUnit_Framework_TestCase;

class ReadableStreamTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->resource = fopen(__FILE__, 'r');
        $this->stream = new ReadableStream($this->resource);
    }

    public function testRead()
    {
        Recoil::run(function () {
            $buffer = '';

            while (!$this->stream->isClosed()) {
                $buffer .= (yield $this->stream->read(16));
            }

            $this->assertSame(file_get_contents(__FILE__), $buffer);
        });
    }

    public function testReadWhenLocked()
    {
        $this->setExpectedException(StreamLockedException::CLASS);

        Recoil::run(function () {
            yield Recoil::execute($this->stream->read(1));

            yield $this->stream->read(1);
        });
    }

    public function testReadWhenClosed()
    {
        $this->setExpectedException(StreamClosedException::CLASS);

        Recoil::run(function () {
            yield $this->stream->close();
            yield $this->stream->read(1);
        });
    }

    public function testClose()
    {
        Recoil::run(function () {
            $this->assertFalse($this->stream->isClosed());

            yield $this->stream->close();

            $this->assertTrue($this->stream->isClosed());

            $this->assertFalse(is_resource($this->resource));
        });
    }

    public function testCloseWithLocked()
    {
        $this->setExpectedException(StreamLockedException::CLASS);

        Recoil::run(function () {
            yield Recoil::execute($this->stream->close());

            yield $this->stream->read(1);
        });
    }
}

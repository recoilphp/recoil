<?php

namespace Recoil\Stream;

use Exception;
use Phake;
use React\EventLoop\StreamSelectLoop;
use Recoil\Recoil;
use Recoil\Stream\Exception\StreamClosedException;
use Recoil\Stream\Exception\StreamLockedException;

trait WritableStreamTestTrait
{
    public function setUp()
    {
        $this->eventLoop = Phake::partialMock(StreamSelectLoop::CLASS);
        $this->path      = tempnam(sys_get_temp_dir(), 'recoil-');
        $this->resource  = fopen($this->path, 'w');
        $this->stream    = $this->createStream();
    }

    public function tearDown()
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    abstract public function createStream();

    public function testWrite()
    {
        Recoil::run(
            function () {
                $buffer = $content = file_get_contents(__FILE__);

                while ($buffer) {
                    $bytesWritten = (yield $this->stream->write($buffer));

                    $this->assertTrue(is_integer($bytesWritten));

                    $buffer = substr($buffer, $bytesWritten);
                }

                yield $this->stream->close();

                $this->assertSame($content, file_get_contents($this->path));
            },
            $this->eventLoop
        );
    }

    public function testWriteWithExplicitLength()
    {
        Recoil::run(
            function () {
                $buffer = $content = file_get_contents(__FILE__);
                $bytesWritten = (yield $this->stream->write($buffer, 16));
                yield $this->stream->close();

                $this->assertSame(16, $bytesWritten);
                $this->assertSame(substr($content, 0, 16), file_get_contents($this->path));
            },
            $this->eventLoop
        );
    }

    public function testWriteWhenLocked()
    {
        $this->setExpectedException(StreamLockedException::CLASS);

        Recoil::run(
            function () {
                yield Recoil::execute($this->stream->write('foo'));

                yield $this->stream->write('foo');
            },
            $this->eventLoop
        );
    }

    public function testWriteWhenClosed()
    {
        $this->setExpectedException(StreamClosedException::CLASS);

        Recoil::run(
            function () {
                yield $this->stream->close();
                yield $this->stream->write('foo');
            },
            $this->eventLoop
        );
    }

    public function testWriteAll()
    {
        Recoil::run(
            function () {
                $content = file_get_contents(__FILE__);

                yield $this->stream->writeAll($content);
                yield $this->stream->close();

                $this->assertSame($content, file_get_contents($this->path));
            },
            $this->eventLoop
        );
    }

    public function testClose()
    {
        Recoil::run(
            function () {
                $this->assertFalse($this->stream->isClosed());

                yield $this->stream->close();

                $this->assertTrue($this->stream->isClosed());

                $this->assertFalse(is_resource($this->resource));
            },
            $this->eventLoop
        );
    }

    public function testCloseWithPendingWrite()
    {
        Recoil::run(
            function () {
                yield Recoil::execute($this->stream->close());

                try {
                    yield $this->stream->write('foo');
                    $this->fail('Expected exception was not thrown.');
                } catch (Exception $e) {
                    $this->setExpectedException(StreamClosedException::CLASS);
                    throw $e;
                }
            },
            $this->eventLoop
        );
    }
}

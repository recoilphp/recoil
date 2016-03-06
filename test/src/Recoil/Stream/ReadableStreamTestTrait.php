<?php

namespace Recoil\Stream;

use Exception;
use Phake;
use React\EventLoop\StreamSelectLoop;
use Recoil\Recoil;
use Recoil\Stream\Exception\StreamClosedException;
use Recoil\Stream\Exception\StreamLockedException;

trait ReadableStreamTestTrait
{
    public function setUp()
    {
        $this->eventLoop = Phake::partialMock(StreamSelectLoop::CLASS);
        $this->path      = __FILE__;
        $this->resource  = fopen($this->path, 'r');
        $this->stream    = $this->createStream();
    }

    abstract public function createStream();

    public function testRead()
    {
        Recoil::run(
            function () {
                $buffer = (yield $this->stream->read(16));

                $length = strlen($buffer);
                $this->assertGreaterThan(0, $length);
                $this->assertLessThanOrEqual(16, $length);

                $expected = substr(file_get_contents($this->path), 0, $length);
                $this->assertSame($expected, $buffer);
            },
            $this->eventLoop
        );
    }

    public function testReadUntilClosed()
    {
        Recoil::run(
            function () {
                $buffer = '';

                while (!$this->stream->isClosed()) {
                    $buffer .= (yield $this->stream->read(16));
                }

                $this->assertSame(file_get_contents($this->path), $buffer);
            },
            $this->eventLoop
        );
    }

    abstract public function testReadFailure();

    public function testReadWhenLocked()
    {
        $this->setExpectedException(StreamLockedException::CLASS);

        Recoil::run(
            function () {
                yield Recoil::execute($this->stream->read(1));

                yield $this->stream->read(1);
            },
            $this->eventLoop
        );
    }

    public function testReadWhenClosed()
    {
        $this->setExpectedException(StreamClosedException::CLASS);

        Recoil::run(
            function () {
                yield $this->stream->close();
                yield $this->stream->read(1);
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

    public function testCloseWithPendingRead()
    {
        Recoil::run(
            function () {
                yield Recoil::execute($this->stream->close());

                try {
                    yield $this->stream->read(1);
                    $this->fail('Expected exception was not thrown.');
                } catch (Exception $e) {
                    $this->setExpectedException(StreamClosedException::CLASS);
                    throw $e;
                }
            },
            $this->eventLoop
        );
    }

    private $eventLoop;
    private $resource;
    private $stream;
}

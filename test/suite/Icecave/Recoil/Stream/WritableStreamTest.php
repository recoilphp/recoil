<?php
namespace Icecave\Recoil\Stream;

use Exception;
use Icecave\Recoil\Recoil;
use Icecave\Recoil\Stream\Exception\StreamClosedException;
use Icecave\Recoil\Stream\Exception\StreamLockedException;
use Phake;
use PHPUnit_Framework_TestCase;
use React\EventLoop\StreamSelectLoop;

class WritableStreamTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->path = tempnam(sys_get_temp_dir(), 'recoil-');
        $this->resource = fopen($this->path, 'w');
        $this->stream = new WritableStream($this->resource);
    }

    public function tearDown()
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    public function testWrite()
    {
        Recoil::run(function () {
            $buffer = $content = file_get_contents(__FILE__);

            while ($buffer) {
                $bytesWritten = (yield $this->stream->write($buffer));
                $buffer = substr($buffer, $bytesWritten);
            }

            $this->assertSame($content, file_get_contents($this->path));
        });
    }

    public function testWriteFailureWithClosedStream()
    {
        $eventLoop = Phake::partialMock(StreamSelectLoop::CLASS);

        Phake::when($eventLoop)
            ->removeWriteStream(Phake::anyParameters())
            ->thenGetReturnByLambda(
                function () {
                    fclose($this->resource);
                }
            );

        $this->setExpectedException(StreamClosedException::CLASS);

        Recoil::run(
            function () {
                yield $this->stream->write(16);
            },
            $eventLoop
        );
    }

    public function testWriteWhenLocked()
    {
        $this->setExpectedException(StreamLockedException::CLASS);

        Recoil::run(function () {
            yield Recoil::execute($this->stream->write('foo'));

            yield $this->stream->write('foo');
        });
    }

    public function testWriteWhenClosed()
    {
        $this->setExpectedException(StreamClosedException::CLASS);

        Recoil::run(function () {
            yield $this->stream->close();
            yield $this->stream->write('foo');
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

            yield $this->stream->write('foo');
        });
    }
}

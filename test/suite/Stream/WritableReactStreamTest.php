<?php

namespace Recoil\Stream;

use Exception;
use PHPUnit_Framework_TestCase;
use React\Stream\Stream;
use Recoil\Recoil;
use Recoil\Stream\Exception\StreamWriteException;

class WritableReactStreamTest extends PHPUnit_Framework_TestCase
{
    use WritableStreamTestTrait;

    public function createStream()
    {
        $this->reactStream = new Stream($this->resource, $this->eventLoop);

        $this->reactStream->getBuffer()->softLimit = 2;

        return new WritableReactStream($this->reactStream);
    }

    public function testWriteLessThanSoftLimit()
    {
        Recoil::run(
            function () {
                $bytesWritten = (yield $this->stream->write('X'));
                yield $this->stream->close();

                $this->assertSame(1, $bytesWritten);
                $this->assertSame('X', file_get_contents($this->path));
            },
            $this->eventLoop
        );
    }

    public function testWriteError()
    {
        $this->setExpectedException(StreamWriteException::class);

        Recoil::run(
            function () {
                $coroutine = function () {
                    $this->stream->onStreamError(new Exception());

                    yield Recoil::noop();
                };

                yield Recoil::execute($coroutine());

                yield $this->stream->write('foo bar');
                yield $this->stream->close();
            },
            $this->eventLoop
        );
    }
}

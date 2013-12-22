<?php
namespace Icecave\Recoil\Stream;

use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;
use React\Stream\Stream;

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
}

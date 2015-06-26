<?php
namespace Recoil\Stream;

use PHPUnit_Framework_TestCase;
use Phake;
use Recoil\Recoil;
use Recoil\Stream\Exception\StreamReadException;

class ReadableStreamTest extends PHPUnit_Framework_TestCase
{
    use ReadableStreamTestTrait;

    public function createStream()
    {
        return new ReadableStream($this->resource);
    }

    public function testReadFailure()
    {
        $this->setExpectedException(StreamReadException::class);

        Phake::when($this->eventLoop)
            ->removeReadStream(Phake::anyParameters())
            ->thenGetReturnByLambda(
                function () {
                    fclose($this->resource);
                }
            );

        Recoil::run(
            function () {
                yield $this->stream->read(16);
            },
            $this->eventLoop
        );
    }
}

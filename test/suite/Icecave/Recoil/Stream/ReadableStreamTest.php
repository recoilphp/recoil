<?php
namespace Icecave\Recoil\Stream;

use Icecave\Recoil\Recoil;
use Icecave\Recoil\Stream\Exception\StreamReadException;
use Phake;
use PHPUnit_Framework_TestCase;

class ReadableStreamTest extends PHPUnit_Framework_TestCase
{
    use ReadableStreamTestTrait;

    public function createStream()
    {
        return new ReadableStream($this->resource);
    }

    public function testReadFailure()
    {
        $this->setExpectedException(StreamReadException::CLASS);

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

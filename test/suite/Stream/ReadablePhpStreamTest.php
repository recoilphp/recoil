<?php

namespace Recoil\Stream;

use Phake;
use PHPUnit_Framework_TestCase;
use Recoil\Recoil;
use Recoil\Stream\Exception\StreamReadException;

class ReadablePhpStreamTest extends PHPUnit_Framework_TestCase
{
    use ReadableStreamTestTrait;

    public function createStream()
    {
        return new ReadablePhpStream($this->resource);
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

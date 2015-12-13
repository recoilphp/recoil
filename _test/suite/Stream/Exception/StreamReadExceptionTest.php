<?php

namespace Recoil\Stream\Exception;

use Exception;
use PHPUnit_Framework_TestCase;

class StreamReadExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $previous  = new Exception();
        $exception = new StreamReadException($previous);

        $this->assertSame('An error occurred while reading from the stream.', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

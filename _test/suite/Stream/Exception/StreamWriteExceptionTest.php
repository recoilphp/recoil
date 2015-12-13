<?php

namespace Recoil\Stream\Exception;

use Exception;
use PHPUnit_Framework_TestCase;

class StreamWriteExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $previous  = new Exception();
        $exception = new StreamWriteException($previous);

        $this->assertSame('An error occurred while writing to the stream.', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

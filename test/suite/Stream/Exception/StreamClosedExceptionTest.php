<?php

namespace Recoil\Stream\Exception;

use Exception;
use PHPUnit_Framework_TestCase;

class StreamClosedExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $previous  = new Exception();
        $exception = new StreamClosedException($previous);

        $this->assertSame('Stream is closed.', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

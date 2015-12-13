<?php

namespace Recoil\Stream\Exception;

use Exception;
use PHPUnit_Framework_TestCase;

class StreamLockedExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $previous  = new Exception();
        $exception = new StreamLockedException($previous);

        $this->assertSame('Stream is already in use by another strand.', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

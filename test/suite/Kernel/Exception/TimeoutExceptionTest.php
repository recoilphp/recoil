<?php

namespace Recoil\Kernel\Exception;

use PHPUnit_Framework_TestCase;

class TimeoutExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $exception = new TimeoutException();

        $this->assertSame('Execution has timed out.', $exception->getMessage());
    }
}

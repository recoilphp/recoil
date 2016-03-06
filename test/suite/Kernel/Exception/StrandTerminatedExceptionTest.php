<?php

namespace Recoil\Kernel\Exception;

use PHPUnit_Framework_TestCase;

class StrandTerminatedExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $exception = new StrandTerminatedException();

        $this->assertSame('Execution has terminated.', $exception->getMessage());
    }
}

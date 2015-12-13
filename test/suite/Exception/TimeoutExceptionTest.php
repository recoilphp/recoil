<?php

declare (strict_types = 1);

namespace Recoil\Exception;

use PHPUnit_Framework_TestCase;

class TimeoutExceptionTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $exception = new TimeoutException(1.237);

        $this->assertSame(
            'The operation timed out after 1.24 second(s).',
            $exception->getMessage()
        );
    }
}

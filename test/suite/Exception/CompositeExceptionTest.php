<?php

declare (strict_types = 1);

namespace Recoil\Exception;

use Exception;
use PHPUnit_Framework_TestCase;

class CompositeExceptionTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $exception1 = new Exception('Exception 1');
        $exception2 = new Exception('Exception 1');

        $exceptions = [
            1 => $exception1,
            0 => $exception2,
        ];

        $exception = new CompositeException($exceptions);

        $this->assertSame(
            'Multiple exceptions occurred.',
            $exception->getMessage()
        );

        $this->assertSame(
            $exceptions,
            $exception->exceptions()
        );
    }
}

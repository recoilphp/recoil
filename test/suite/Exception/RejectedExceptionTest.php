<?php

declare (strict_types = 1);

namespace Recoil\Exception;

use PHPUnit_Framework_TestCase;

class RejectedExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testString()
    {
        $exception = new RejectedException('<string>');

        $this->assertSame(
            '<string>',
            $exception->getMessage()
        );
    }

    public function testInteger()
    {
        $exception = new RejectedException(123);

        $this->assertSame(
            'The promise was rejected (123).',
            $exception->getMessage()
        );

        $this->assertSame(
            123,
            $exception->getCode()
        );
    }

    public function testOther()
    {
        $exception = new RejectedException([1, 2, 3]);

        $this->assertSame(
            'The promise was rejected ([1, 2, 3]).',
            $exception->getMessage()
        );

        $this->assertSame(
            [1, 2, 3],
            $exception->reason()
        );
    }
}

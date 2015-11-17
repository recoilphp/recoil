<?php

namespace Recoil\Coroutine\Exception;

use PHPUnit_Framework_TestCase;

class PromiseRejectedExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $exception = new PromiseRejectedException('Rejection reason.');

        $this->assertSame('Promise was rejected: "Rejection reason.".', $exception->getMessage());
        $this->assertSame('Rejection reason.', $exception->reason());
    }
}

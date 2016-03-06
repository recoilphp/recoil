<?php

declare (strict_types = 1);

namespace Recoil\Exception;

use Eloquent\Phony\Phpunit\Phony;
use PHPUnit_Framework_TestCase;
use Throwable;

class CompositeExceptionTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $exception1 = Phony::mock(Throwable::class)->mock();
        $exception2 = Phony::mock(Throwable::class)->mock();

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

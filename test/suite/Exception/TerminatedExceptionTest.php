<?php

declare (strict_types = 1);

namespace Recoil\Exception;

use Eloquent\Phony\Phpunit\Phony;
use PHPUnit_Framework_TestCase;
use Recoil\Kernel\Strand;

class TerminatedExceptionTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $strand = Phony::mock(Strand::class);
        $strand->id->returns(123);

        $exception = new TerminatedException($strand->mock());

        $this->assertSame(
            'Strand #123 was terminated.',
            $exception->getMessage()
        );

        $this->assertSame(
            $strand->mock(),
            $exception->strand()
        );
    }
}

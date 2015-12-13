<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Eloquent\Phony\Phpunit\Phony;
use Exception;
use PHPUnit_Framework_TestCase;

class StrandTraitTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->caller = Phony::mock(Suspendable::class);

        $this->subject = Phony::partialMock(StrandTrait::class);
    }

    public function testTerminate()
    {
        $this->markTestSkipped();
    }

    public function testAwaitable()
    {
        $this->markTestSkipped();
    }

    public function testResume()
    {
        $this->subject->mock()->resume('<result>');

        $this->subject->finalize->calledWith(null, '<result>');
    }

    public function testThrow()
    {
        $exception = new Exception('<exception>');

        try {
            $this->subject->mock()->throw($exception);
            $this->fail('Expected exception was not thrown.');
        } catch (Exception $e) {
            $this->subject->finalize->calledWith($exception, null);
            $this->assertSame($exception, $e);
        }
    }

    public function testThrowWhenCaptured()
    {
        $this->markTestSkipped();
    }
}

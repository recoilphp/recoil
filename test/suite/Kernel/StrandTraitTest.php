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
        $this->caller1 = Phony::mock(Suspendable::class);
        $this->caller2 = Phony::mock(Suspendable::class);
        $this->api = Phony::mock(Api::class);

        $this->subject = Phony::partialMock(StrandTrait::class);
    }

    public function testTerminate()
    {
        $this->markTestSkipped();
    }

    public function testResume()
    {
        $this->subject->mock()->await($this->caller1->mock(), $this->api->mock());
        $this->subject->mock()->await($this->caller2->mock(), $this->api->mock());

        $this->subject->mock()->resume('<result>');

        $this->caller1->resume->calledWith('<result>');
        $this->caller2->resume->calledWith('<result>');

        $this->subject->finalize->calledWith(null, '<result>');
    }

    public function testThrow()
    {
        $this->subject->mock()->await($this->caller1->mock(), $this->api->mock());
        $this->subject->mock()->await($this->caller2->mock(), $this->api->mock());

        $exception = new Exception('<exception>');

        try {
            $this->subject->mock()->throw($exception);
            $this->fail('Expected exception was not thrown.');
        } catch (Exception $e) {
            $this->caller1->throw->calledWith($exception);
            $this->caller2->throw->calledWith($exception);
            $this->subject->finalize->calledWith($exception, null);
            $this->assertSame($exception, $e);
        }
    }

    public function testThrowWhenCaptured()
    {
        $this->markTestSkipped();
    }
}

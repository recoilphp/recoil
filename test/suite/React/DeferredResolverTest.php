<?php

declare (strict_types = 1);

namespace Recoil\React;

use Eloquent\Phony\Phpunit\Phony;
use PHPUnit_Framework_TestCase;
use React\Promise\Deferred;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Strand;
use Throwable;

class DeferredResolverTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->deferred = Phony::mock(Deferred::class);
        $this->strand = Phony::mock(Strand::class);
        $this->strand->id->returns(1);

        $this->subject = new DeferredResolver(
            $this->deferred->mock()
        );
    }

    public function testSuccess()
    {
        $this->subject->success(
            $this->strand->mock(),
            '<value>'
        );

        $this->deferred->resolve->calledWith('<value>');
    }

    public function testFailure()
    {
        $exception = Phony::mock(Throwable::class);

        $this->subject->failure(
            $this->strand->mock(),
            $exception->mock()
        );

        $this->deferred->reject->calledWith($exception->mock());
    }

    public function testTerminated()
    {
        $this->subject->terminated(
            $this->strand->mock()
        );

        $this->deferred->reject->calledWith(
            new TerminatedException($this->strand->mock())
        );
    }
}

<?php

declare (strict_types = 1);

namespace Recoil\React;

use Eloquent\Phony\Phpunit\Phony;
use PHPUnit_Framework_TestCase;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Kernel;
use Recoil\PromiseTestTrait;
use Throwable;

class ReactStrandTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = Phony::mock(Kernel::class);
        $this->api = Phony::mock(Api::class);

        $this->subject = new ReactStrand(
            1,
            $this->kernel->mock(),
            $this->api->mock()
        );
    }

    public function testPromise()
    {
        $promise = $this->subject->promise();

        $this->subject->start(
            function () {
                return '<value>';
                yield;
            }
        );

        $this->assertResolvedWith(
            '<value>',
            $promise
        );
    }

    public function testPromiseWithStrandFailure()
    {
        $promise = $this->subject->promise();
        $exception = Phony::mock(Throwable::class)->mock();

        $this->subject->start(
            function () use ($exception) {
                throw $exception;
                yield;
            }
        );

        $this->assertRejectedWith(
            $exception,
            $promise
        );
    }

    public function testPromiseWhenTerminated()
    {
        $promise = $this->subject->promise();

        $this->subject->terminate();

        $this->assertRejectedWith(
            new TerminatedException($this->subject),
            $promise
        );
    }

    use PromiseTestTrait;
}

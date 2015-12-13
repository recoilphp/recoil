<?php

declare (strict_types = 1);

namespace Recoil\React;

use Eloquent\Phony\Phpunit\Phony;
use Exception;
use PHPUnit_Framework_TestCase;
use React\Promise\ExtendedPromiseInterface;

class ReactStrandTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->subject = new ReactStrand();
    }

    public function testThrowWithoutCaptureIsPropagated()
    {
        $this->setExpectedException(
            Exception::class,
            '<exception>'
        );

        $exception = new Exception('<exception>');

        $this->subject->throw($exception);
    }

    public function testCapture()
    {
        $resolve = Phony::spy();
        $reject = Phony::spy();

        $promise = $this->subject->capture();

        $this->assertInstanceOf(
            ExtendedPromiseInterface::class,
            $promise
        );

        $promise->done($resolve, $reject);

        $resolve->never()->called();

        $this->subject->resume('<value>');

        $resolve->calledWith('<value>');
        $reject->never()->called();
    }

    public function testCaptureWithThrow()
    {
        $resolve = Phony::spy();
        $reject = Phony::spy();

        $promise = $this->subject->capture();

        $this->assertInstanceOf(
            ExtendedPromiseInterface::class,
            $promise
        );

        $promise->done($resolve, $reject);

        $resolve->never()->called();

        $exception = new Exception('<exception>');

        $this->subject->throw($exception);

        $resolve->never()->called();
        $reject->calledWith($exception);
    }
}

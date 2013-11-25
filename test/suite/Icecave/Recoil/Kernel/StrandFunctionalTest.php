<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;

class StrandFunctionalTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel;
    }

    /**
     * Test that strands of execution can be suspended and resumed.
     */
    public function testSuspendAndResume()
    {
        $this->expectOutputString('12345');

        $strand = null;

        $coroutine = function () use (&$strand) {
            echo 1;
            echo (yield Recoil::suspend(
                function ($s) use (&$strand) {
                    echo 2;
                    $strand = $s;
                }
            ));
        };

        $resumer = function () use (&$strand) {
            echo 3;
            $strand->resume(5);
            echo 4;

            return; yield; // make this closure a generator
        };

        $this->kernel->execute($coroutine());
        $this->kernel->execute($resumer());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that strands of execution can be suspended and resumed with an
     * exception.
     */
    public function testSuspendAndResumeWithException()
    {
        $this->expectOutputString('12345');

        $strand = null;

        $coroutine = function () use (&$strand) {
            echo 1;
            try {
                yield Recoil::suspend(
                    function ($s) use (&$strand) {
                        echo 2;
                        $strand = $s;
                    }
                );
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        };

        $resumer = function () use (&$strand) {
            echo 3;
            $strand->resumeWithException(new Exception(5));
            echo 4;

            return; yield; // make this closure a generator
        };

        $this->kernel->execute($coroutine());
        $this->kernel->execute($resumer());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that a strand can be used as a promise.
     */
    public function testPromise()
    {
        $coroutine = function () {
            yield Recoil::return_(123);
        };

        $strand = $this->kernel->execute($coroutine());

        $value = null;
        $strand->then(
            function ($v) use (&$value) {
                $value = $v;
            }
        );

        $this->kernel->eventLoop()->run();

        $this->assertSame(123, $value);
    }

    /**
     * Test that exceptions are still propagated when a strand is used as a
     * promise but no error handler is provided.
     */
    public function testPromiseWithNoErrorHandler()
    {
        $coroutine = function () {
            yield Recoil::throw_(new Exception('This is the exception.'));
        };

        $strand = $this->kernel->execute($coroutine());

        $strand->then();

        $this->setExpectedException('Exception', 'This is the exception.');
        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that a strand used as a promise with an error handler prevents
     * exceptions from propagating.
     */
    public function testStrandAsPromiseWithErrorHandler()
    {
        $coroutine = function () {
            yield Recoil::throw_(new Exception('This is the exception.'));
        };

        $strand = $this->kernel->execute($coroutine());

        $exception = null;
        $strand->then(
            null,
            function ($error) use (&$exception) {
                $exception = $error;
            }
        );

        $this->kernel->eventLoop()->run();

        $this->assertInstanceOf('Exception', $exception);
        $this->assertSame('This is the exception.', $exception->getMessage());
    }

    /**
     * Test that a strand can be used as a promise after the strand has
     * terminated.
     */
    public function testPromiseResolvedAfterStrandTerminated()
    {
        $coroutine = function () {
            yield Recoil::return_(123);
        };

        $strand = $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $value = null;
        $strand->then(
            function ($v) use (&$value) {
                $value = $v;
            }
        );

        $this->assertSame(123, $value);
    }

    /**
     * Test that a strand can be used as a promise after the strand has
     * terminated.
     */
    public function testPromiseRejectedAfterStrandTerminated()
    {
        $coroutine = function () {
            yield Recoil::throw_(new Exception('This is the exception.'));
        };

        $strand = $this->kernel->execute($coroutine());

        try {
            $this->kernel->eventLoop()->run();
        } catch (Exception $e) {
            // ignore
        }

        $exception = null;
        $strand->then(
            null,
            function ($error) use (&$exception) {
                $exception = $error;
            }
        );

        $this->assertInstanceOf('Exception', $exception);
        $this->assertSame('This is the exception.', $exception->getMessage());
    }
}

<?php
namespace Recoil\Coroutine;

use Exception;
use Recoil\Recoil;
use Recoil\Kernel\Kernel;
use PHPUnit_Framework_TestCase;

/**
 * @covers Recoil\Coroutine\GeneratorCoroutine
 * @covers Recoil\Coroutine\CoroutineTrait
 */
class GeneratorCoroutineTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel();
    }

    public function testCall()
    {
        $this->expectOutputString('1');

        $coroutine = function () {
            echo 1;

            return; yield; // make this closure a generator
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testThrowAfterCall()
    {
        $coroutine = function () {
            throw new Exception('This is the exception.');
            yield; // make this closure a generator
        };

        $this->kernel->execute($coroutine());

        $this->setExpectedException('Exception', 'This is the exception.');
        $this->kernel->eventLoop()->run();
    }

    public function testResumeWithValue()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            echo 1;
            echo (yield Recoil::suspend(
                function ($strand) {
                    $strand->resumeWithValue(2);
                }
            ));
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testThrowAfterResumeWithValue()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            echo 1;
            echo (yield Recoil::suspend(
                function ($strand) {
                    $strand->resumeWithValue(2);
                }
            ));
            throw new Exception('This is the exception.');
        };

        $this->kernel->execute($coroutine());

        $this->setExpectedException('Exception', 'This is the exception.');
        $this->kernel->eventLoop()->run();
    }

    public function testResumeWithException()
    {
        $this->expectOutputString('1');

        $exception = null;
        $coroutine = function () use (&$exception) {
            echo 1;

            try {
                yield Recoil::suspend(
                    function ($strand) {
                        $strand->resumeWithException(
                            new Exception('This is the exception.')
                        );
                    }
                );
            } catch (Exception $e) {
                $exception = $e;
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertInstanceOf('Exception', $exception);
        $this->assertSame('This is the exception.', $exception->getMessage());
    }

    public function testThrowAfterResumeWithException()
    {
        $this->expectOutputString('1');

        $coroutine = function () {
            echo 1;

            // Let this exception propagate ...
            yield Recoil::suspend(
                function ($strand) {
                    $strand->resumeWithException(
                        new Exception('This is the exception.')
                    );
                }
            );
        };

        $this->kernel->execute($coroutine());

        $this->setExpectedException('Exception', 'This is the exception.');
        $this->kernel->eventLoop()->run();
    }

    public function testTerminate()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            try {
                echo 1;
                yield Recoil::suspend(
                    function ($strand) {
                        $strand->terminate();
                    }
                );
                echo 'X';
            } finally {
                echo 2;
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }
}

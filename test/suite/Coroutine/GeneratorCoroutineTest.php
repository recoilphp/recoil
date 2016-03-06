<?php

namespace Recoil\Coroutine;

use Exception;
use PHPUnit_Framework_TestCase;
use Recoil\Kernel\StandardKernel;
use Recoil\Recoil;

/**
 * @covers Recoil\Coroutine\GeneratorCoroutine
 * @covers Recoil\Coroutine\CoroutineTrait
 */
class GeneratorCoroutineTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new StandardKernel();
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
            echo(yield Recoil::suspend(
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
            echo(yield Recoil::suspend(
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

    /**
     * @requires PHP 7
     */
    public function testGeneratorReturn()
    {
        $this->assertGeneratorReturn(
            "
            function () {
                return '<result>';
                yield;
            }
            "
        );
    }

    /**
     * @requires PHP 7
     */
    public function testGeneratorReturnOnResumeWithValue()
    {
        $this->assertGeneratorReturn(
            "
            function () {
                yield;
                return '<result>';
            }
            "
        );
    }

    /**
     * @requires PHP 7
     */
    public function testGeneratorReturnOnResumeWithException()
    {
        $this->assertGeneratorReturn(
            "
            function () {
                try {
                    \$thrower = function () {
                        throw new Exception('The exception.');
                        yield;
                    };

                    yield \$thrower();
                } catch (Exception \$e) {
                    return '<result>';
                }
            }
            "
        );
    }

    private function assertGeneratorReturn($coroutine)
    {
        $coroutine = eval('return ' . $coroutine . ';');

        $result = null;
        $strand = $this->kernel->execute($coroutine());
        $strand->on(
            'success',
            function ($strand, $value) use (&$result) {
                $result = $value;
            }
        );

        $this->kernel->eventLoop()->run();

        $this->assertSame(
            '<result>',
            $result
        );
    }
}

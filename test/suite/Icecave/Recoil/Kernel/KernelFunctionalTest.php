<?php
namespace Icecave\Recoil\Kernel;

use BadMethodCallException;
use Exception;
use Icecave\Recoil\Recoil;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use React\Promise\FulfilledPromise;

class KernelFunctionalTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel;
    }

    /**
     * Test that a simple (single-tick) co-routine can be executed by the kernel.
     */
    public function testExecute()
    {
        $this->expectOutputString('1');

        $coroutine = function () {
            echo 1;

            return; yield; // enforce generator
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that one co-routine can call another.
     */
    public function testCall()
    {
        $this->expectOutputString('123');

        $coroutine = function () {
            $f = function () {
                echo 2;

                return; yield; // enforce generator
            };

            echo 1;
            yield $f();
            echo 3;
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that one co-routine can return a value to the caller and resume.
     */
    public function testReturn()
    {
        $this->expectOutputString('12345');

        $coroutine = function () {
            $f = function () {
                echo 2;
                yield Recoil::return_(4);
                echo 3;
            };

            echo 1;
            echo (yield $f());
            echo 5;
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that one co-routine can throw an exception to the caller and resume.
     */
    public function testThrow()
    {
        $this->expectOutputString('12345');

        $coroutine = function () {
            $f = function () {
                echo 2;
                yield Recoil::throw_(new Exception(4));
                echo 3;
            };

            echo 1;
            try {
                yield $f();
            } catch (Exception $e) {
                echo $e->getMessage();
            }
            echo 5;
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that uncaught exceptions propagate.
     */
    public function testExceptionsPropagate()
    {
        $this->expectOutputString('This is the exception.');

        $coroutine = function () {
            throw new Exception('This is the exception.');
            yield; // enforce generator
        };

        $this->kernel->execute($coroutine());

        try {
            $this->kernel->eventLoop()->run();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Test that exceptions thrown via PHP's own throw keyword are propagated to
     * the calling co-routine.
     */
    public function testExceptionsPropagateToCaller()
    {
        $this->expectOutputString('This is the exception.');

        $coroutine = function () {
            $f = function () {
                throw new Exception('This is the exception.');
                yield; // enforce generator
            };

            try {
                yield $f();
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that co-routines are resumed with an appropriate exception when
     * they yield a value that can not be adapted into a co-routine.
     */
    public function testAdaptationFailure()
    {
        $coroutine = function () {
            try {
                yield 123;
                $this->fail('Expected exception was not thrown.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(
                    'Unable to adapt 123 into a co-routine.',
                    $e->getMessage()
                );
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that co-routines are resuemd with an appropriate exception when
     * invoking an unknown system-call via the Recoil facade.
     */
    public function testUnknownSystemCall()
    {
        $coroutine = function () {
            try {
                yield Recoil::foo();
                $this->fail('Expected exception was not thrown.');
            } catch (BadMethodCallException $e) {
                $this->assertSame(
                    'Kernel API does not support the "foo" system-call.',
                    $e->getMessage()
                );
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that strands can be terminate mid-execution.
     */
    public function testTerminate()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            $f = function () {
                echo 2;
                yield Recoil::terminate();
                echo 'X';
            };

            echo 1;
            yield $f();
            echo 'X';
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that strands of can use Recoil::sleep() to suspend execution for a
     * set number of seconds.
     */
    public function testSleep()
    {
        $start = 0;
        $end = 0;

        $coroutine = function () use (&$start, &$end) {
            $start = microtime(true);
            yield Recoil::sleep(0.15);
            $end = microtime(true);
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertEquals(0.15, $end - $start, '', 0.01);
    }

    /**
     * Test that strands of execution can be suspended and resumed.
     */
    public function testResume()
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

            return; yield; // enforce generator
        };

        $this->kernel->execute($coroutine());
        $this->kernel->execute($resumer());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that strands of execution can be suspended and resumed with an
     * exception.
     */
    public function testResumeWithException()
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

            return; yield; // enforce generator
        };

        $this->kernel->execute($coroutine());
        $this->kernel->execute($resumer());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that strands can 'co-operate' (give control to another strand) using
     * Recoil::cooperate().
     */
    public function testCooperate()
    {
        $this->expectOutputString('1A2A1B2B');

        $coroutine = function ($id) {
            echo $id . 'A';
            yield Recoil::cooperate();
            echo $id . 'B';
        };

        $this->kernel->execute($coroutine(1));
        $this->kernel->execute($coroutine(2));

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that strands can 'co-operate' (give control to another strand) by
     * yielding null.
     */
    public function testCooperateWithNull()
    {
        $this->expectOutputString('1A2A1B2B');

        $coroutine = function ($id) {
            echo $id . 'A';
            yield;
            echo $id . 'B';
        };

        $this->kernel->execute($coroutine(1));
        $this->kernel->execute($coroutine(2));

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that strands can perform a 'no-op' yield that does not give control
     * to another strand.
     */
    public function testNoOp()
    {
        $this->expectOutputString('1A1B2A2B');

        $coroutine = function ($id) {
            echo $id . 'A';
            yield Recoil::noop();
            echo $id . 'B';
        };

        $this->kernel->execute($coroutine(1));
        $this->kernel->execute($coroutine(2));

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that a ReactPHP promise can be yielded directly.
     */
    public function testPromise()
    {
        $value = null;
        $coroutine = function () use (&$value) {
            $value = (yield new FulfilledPromise(123));
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertSame(123, $value);
    }
}

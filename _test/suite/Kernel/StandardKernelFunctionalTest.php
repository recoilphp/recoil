<?php

namespace Recoil\Kernel;

use Exception;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use React\Promise\FulfilledPromise;

/**
 * @group functional
 */
class StandardKernelFunctionalTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new StandardKernel();
    }

    /**
     * Test that a simple (single-tick) coroutine can be executed by the kernel.
     */
    public function testExecute()
    {
        $this->expectOutputString('1');

        $coroutine = function () {
            echo 1;

            return; yield; // make this closure a generator
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that one coroutine can call another by yielding it.
     */
    public function testYieldGenerator()
    {
        $this->expectOutputString('123');

        $coroutine = function () {
            $f = function () {
                echo 2;

                return; yield; // make this closure a generator
            };

            echo 1;
            yield $f();
            echo 3;
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that strands can 'co-operate' (give control to another strand) by
     * yielding null.
     */
    public function testYieldNull()
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
     * Test that a React promise can be yielded directly.
     */
    public function testYieldPromise()
    {
        $value     = null;
        $coroutine = function () use (&$value) {
            $value = (yield new FulfilledPromise(123));
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertSame(123, $value);
    }

    /**
     * Test that coroutines are resumed with an appropriate exception when
     * they yield a value that can not be adapted into a coroutine.
     */
    public function testAdaptationFailure()
    {
        $coroutine = function () {
            try {
                yield 123;
                $this->fail('Expected exception was not thrown.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(
                    'Unable to adapt 123 into a coroutine.',
                    $e->getMessage()
                );
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that uncaught exceptions propagate outside the kernel.
     */
    public function testExceptionsPropagate()
    {
        $coroutine = function () {
            throw new Exception('This is the exception.');

            return; yield; // make this closure a generator
        };

        $this->kernel->execute($coroutine());

        $this->setExpectedException('Exception', 'This is the exception.');
        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that exceptions thrown inside a nested coroutine are propagated to
     * the calling coroutine.
     */
    public function testExceptionsPropagateToCaller()
    {
        $this->expectOutputString('This is the exception.');

        $coroutine = function () {
            $f = function () {
                throw new Exception('This is the exception.');

                return; yield; // make this closure a generator
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
}

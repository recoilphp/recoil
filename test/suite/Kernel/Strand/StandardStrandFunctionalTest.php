<?php

namespace Recoil\Kernel\Strand;

use Exception;
use PHPUnit_Framework_TestCase;
use Recoil\Kernel\StandardKernel;
use Recoil\Recoil;

/**
 * @group functional
 */
class StandardStrandFunctionalTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new StandardKernel();
    }

    /**
     * Test that strands of execution can be suspended and resumed.
     */
    public function testSuspendAndResumeWithValue()
    {
        $this->expectOutputString('12345');

        $strand = null;

        $coroutine = function () use (&$strand) {
            echo 1;
            echo(yield Recoil::suspend(
                function ($s) use (&$strand) {
                    echo 2;
                    $strand = $s;
                }
            ));
        };

        $resumer = function () use (&$strand) {
            echo 3;
            $strand->resumeWithValue(5);
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
     * Test that strands of execution can be suspended and resumed before being started.
     */
    public function testSuspendAndResume()
    {
        $this->expectOutputString('123');

        $strand = null;

        $coroutine = function () use (&$strand) {
            echo 3;

            return; yield; // make this closure a generator
        };

        $resumer = function () use (&$strand) {
            echo 1;
            $strand->resumeWithValue(null);
            echo 2;

            return; yield; // make this closure a generator
        };

        $strand = $this->kernel->execute($coroutine());
        $strand->suspend();

        $this->kernel->execute($resumer());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that strands can be terminated, and that no exception is propagated.
     */
    public function testTerminate()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            try {
                echo 1;
                yield Recoil::terminate();
                echo 'X';
            } finally {
                echo 2;
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test that the hasExited() method returns true after execution of the
     * strand has completed.
     */
    public function testHasExited()
    {
        $coroutine = function () {
            yield Recoil::noop();
        };

        $strand = $this->kernel->execute($coroutine());

        $this->assertFalse($strand->hasExited());

        $this->kernel->eventLoop()->run();

        $this->assertTrue($strand->hasExited());
    }

    /**
     * Test that the hasExited() method returns true after execution of the
     * strand has completed due to an exception.
     */
    public function testHasExitedWithException()
    {
        $coroutine = function () {
            yield Recoil::throw_(new Exception('This is the exception.'));
        };

        $strand = $this->kernel->execute($coroutine());

        $this->assertFalse($strand->hasExited());

        try {
            $this->kernel->eventLoop()->run();
        } catch (Exception $e) {
            if ($e->getMessage() !== 'This is the exception.') {
                throw $e;
            }
        }

        $this->assertTrue($strand->hasExited());
    }

    /**
     * Test that the hasExited() method returns true after execution of the
     * strand has completed due to termination.
     */
    public function testHasExitedWhenTerminated()
    {
        $coroutine = function () {
            yield Recoil::terminate();
        };

        $strand = $this->kernel->execute($coroutine());

        $this->assertFalse($strand->hasExited());

        $this->kernel->eventLoop()->run();

        $this->assertTrue($strand->hasExited());
    }

    /**
     * Test the success event.
     */
    public function testSuccessEvent()
    {
        $this->expectOutputString('123X');

        $coroutine = function () {
            yield Recoil::return_(123);
        };

        $strand = $this->kernel->execute($coroutine());

        $strand->on('success', function ($eventStrand, $value) use ($strand) {
            $this->assertSame($strand, $eventStrand);
            echo $value;
        });

        $strand->on('exit', function ($eventStrand) use ($strand) {
            $this->assertSame($strand, $eventStrand);
            echo 'X';
        });

        $strand->on('suspend', function () {
            echo '!'; // This should not be fired.
        });

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test the error event.
     */
    public function testErrorEvent()
    {
        $this->expectOutputString('EX');

        $exception = new Exception('This is the exception.');

        $coroutine = function () use ($exception) {
            yield Recoil::throw_($exception);
        };

        $strand = $this->kernel->execute($coroutine());

        $strand->on('error', function ($eventStrand, $eventException) use ($strand, $exception) {
            $this->assertSame($strand, $eventStrand);
            $this->assertSame($exception, $eventException);
            echo 'E';
        });

        $strand->on('exit', function ($eventStrand) use ($strand) {
            $this->assertSame($strand, $eventStrand);
            echo 'X';
        });

        $strand->on('suspend', function () {
            echo '!'; // This should not be fired.
        });

        try {
            $this->kernel->eventLoop()->run();
            $this->fail('Expected exception was not thrown.');
        } catch (Exception $e) {
            $this->assertSame('This is the exception.', $e->getMessage());
        }
    }

    /**
     * Test the error event.
     */
    public function testErrorEventWithoutRethrow()
    {
        $this->expectOutputString('EX');

        $coroutine = function () {
            yield Recoil::throw_(new Exception('This is the exception.'));
        };

        $strand = $this->kernel->execute($coroutine());

        $strand->on('error', function ($strand, $exception, $preventDefault) {
            $preventDefault();
            echo 'E';
        });

        $strand->on('exit', function ($eventStrand) use ($strand) {
            $this->assertSame($strand, $eventStrand);
            echo 'X';
        });

        $strand->on('suspend', function () {
            echo '!'; // This should not be fired.
        });

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test the terminate event.
     */
    public function testTerminateEvent()
    {
        $this->expectOutputString('TX');

        $coroutine = function () {
            yield Recoil::terminate();
        };

        $strand = $this->kernel->execute($coroutine());

        $strand->on('terminate', function ($eventStrand) use ($strand) {
            $this->assertSame($strand, $eventStrand);
            echo 'T';
        });

        $strand->on('exit', function ($eventStrand) use ($strand) {
            $this->assertSame($strand, $eventStrand);
            echo 'X';
        });

        $this->kernel->eventLoop()->run();
    }

    public function testTickWithoutAction()
    {
        $strand = new StandardStrand($this->kernel);

        $this->setExpectedException(
            'LogicException',
            'No action has been requested.'
        );

        $strand->tick();
    }
}

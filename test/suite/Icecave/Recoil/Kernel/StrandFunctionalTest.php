<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;

/**
 * @group functional
 */
class StrandFunctionalTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel;
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
            echo (yield Recoil::suspend(
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
            $strand->resume();
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
     * Test that the 'suspend' and 'resume' events are fired as appropriate.
     */
    public function testSuspendAndResumeEvents()
    {
        $this->expectOutputString('1S2R3');

        $coroutine = function () {
            return; yield; // make this closure a generator
        };

        $strand = $this->kernel->execute($coroutine());

        $strand->on('suspend', function () {
            echo 'S';
        });

        $strand->on('resume', function () {
            echo 'R';
        });

        echo 1;

        $strand->suspend();

        echo 2;

        $strand->resume();

        echo 3;
    }

    /**
     * Test the exit event.
     */
    public function testExitEvent()
    {
        $this->expectOutputString('123');

        $coroutine = function () {
            yield Recoil::return_(123);
        };

        $strand = $this->kernel->execute($coroutine());

        $strand->on('exit', function ($eventStrand, $value) use ($strand) {
            $this->assertSame($strand, $eventStrand);
            echo $value;
        });

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test the error event.
     */
    public function testErrorEvent()
    {
        $this->expectOutputString('error');

        $exception = new Exception('This is the exception.');

        $coroutine = function () use ($exception) {
            yield Recoil::throw_($exception);
        };

        $strand = $this->kernel->execute($coroutine());

        $strand->on('error', function ($eventStrand, $eventException) use ($strand, $exception) {
            $this->assertSame($strand, $eventStrand);
            $this->assertSame($exception, $eventException);
            echo 'error';
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
        $this->expectOutputString('error');

        $coroutine = function () {
            yield Recoil::throw_(new Exception('This is the exception.'));
        };

        $strand = $this->kernel->execute($coroutine());

        $strand->on('error', function ($strand, $exception, $preventDefault) {
            $preventDefault();
            echo 'error';
        });

        $this->kernel->eventLoop()->run();
    }

    /**
     * Test the terminate event.
     */
    public function testTerminateEvent()
    {
        $this->expectOutputString('terminated');

        $coroutine = function () {
            yield Recoil::terminate();
        };

        $strand = $this->kernel->execute($coroutine());

        $strand->on('terminate', function ($eventStrand) use ($strand) {
            $this->assertSame($strand, $eventStrand);
            echo 'terminated';
        });

        $this->kernel->eventLoop()->run();
    }
}

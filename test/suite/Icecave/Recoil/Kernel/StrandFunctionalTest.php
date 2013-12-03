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
}

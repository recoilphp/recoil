<?php
namespace Icecave\Recoil\Kernel\Api;

use Exception;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;

class SleepTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel;
    }

    public function testSleep()
    {
        $start = 0;
        $end = 0;

        $coroutine = function () use (&$start, &$end) {
            $start = microtime(true);
            yield new Sleep(0.15);
            $end = microtime(true);
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertEquals(0.15, $end - $start, '', 0.01);
    }

    public function testResumeBeforeTimeout()
    {
        $start = 0;
        $end = 0;

        $coroutine = function () use (&$start, &$end) {
            $start = microtime(true);
            $this->assertSame(123, (yield new Sleep(0.15)));
            $end = microtime(true);
        };

        $strand = $this->kernel->execute($coroutine());

        $canceller = function () use ($strand) {
            $strand->resume(123);

            return; yield; // make this closure a generator
        };

        $this->kernel->execute($canceller());

        $this->kernel->eventLoop()->run();

        $this->assertEquals(0, $end - $start, '', 0.01);
    }

    public function testResumeWithExceptionBeforeTimeout()
    {
        $start = 0;
        $end = 0;

        $coroutine = function () use (&$start, &$end) {
            $start = microtime(true);
            try {
                yield new Sleep(0.15);
            } catch (Exception $e) {
                $this->assertSame('This is the exception.', $e->getMessage());
                $end = microtime(true);
            }
        };

        $strand = $this->kernel->execute($coroutine());

        $canceller = function () use ($strand) {
            $strand->resumeWithException(
                new Exception('This is the exception.')
            );

            return; yield; // make this closure a generator
        };

        $this->kernel->execute($canceller());

        $this->kernel->eventLoop()->run();

        $this->assertEquals(0, $end - $start, '', 0.01);
    }

    public function testTerminateBeforeTimeout()
    {
        $resumed = null;
        $coroutine = function () use (&$resumed) {
            $resumed = false;
            yield new Sleep(0.15);
            $resumed = true;
        };

        $strand = $this->kernel->execute($coroutine());

        $canceller = function () use ($strand) {
            $strand->terminate();

            return; yield; // make this closure a generator
        };

        $this->kernel->execute($canceller());

        $this->kernel->eventLoop()->run();

        $this->assertFalse($resumed);
    }
}

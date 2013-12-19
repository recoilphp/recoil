<?php
namespace Icecave\Recoil\Kernel\Api;

use Exception;
use Icecave\Recoil\Kernel\Exception\TimeoutException;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Kernel\Strand\StrandInterface;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;

class KernelApiTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel;
    }

    public function testStrand()
    {
        $strand = null;
        $coroutine = function () use (&$strand) {
            $strand = (yield Recoil::strand());
        };

        $expectedStrand = $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertSame($expectedStrand, $strand);
    }

    public function testKernel()
    {
        $kernel = null;
        $coroutine = function () use (&$kernel) {
            $kernel = (yield Recoil::kernel());
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertSame($this->kernel, $kernel);
    }

    public function testEventLoop()
    {
        $eventLoop = null;
        $coroutine = function () use (&$eventLoop) {
            $eventLoop = (yield Recoil::eventLoop());
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertSame($this->kernel->eventLoop(), $eventLoop);
    }

    public function testReturn()
    {
        $this->expectOutputString('1');

        $coroutine = function () {
            $f = function () {
                yield Recoil::return_(1);
            };

            echo (yield $f());
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testThrow()
    {
        $this->expectOutputString('1');

        $coroutine = function () {
            $f = function () {
                yield Recoil::throw_(new Exception(1));
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

    public function testSuspend()
    {
        $this->expectOutputString('');

        $strand = null;

        $coroutine = function () use (&$strand) {
            yield Recoil::suspend(
                function ($s) use (&$strand) {
                    $strand = $s;
                }
            );
            echo 'X';
        };

        $expectedStrand = $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertSame($expectedStrand, $strand);
    }

    public function testTimeout()
    {
        $this->expectOutputString('1');

        $immediate = function () {
            yield Recoil::return_(1);
        };

        $coroutine = function () use ($immediate) {
            echo (yield Recoil::timeout(0.01, $immediate()));
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testTimeoutExceeded()
    {
        $this->expectOutputString('1');

        $forever = function () {
            yield Recoil::suspend(function () {});
        };

        $coroutine = function () use ($forever) {
            try {
                yield Recoil::timeout(0.01, $forever());
            } catch (TimeoutException $e) {
                echo 1;
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

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

    public function testExecute()
    {
        $this->expectOutputString('123');

        $strand = null;
        $coroutine = function () use (&$strand) {
            $f = function () {
                echo 3;
                yield Recoil::noop();
            };

            echo 1;
            $strand = (yield Recoil::execute($f()));
            echo 2;
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertInstanceOf(StrandInterface::CLASS, $strand);
    }

    public function testSelect()
    {
        $this->expectOutputString('123');

        $coroutine = function () {
            $f = function () {
                echo 2;
                yield Recoil::noop();
            };

            $strand = (yield Recoil::execute($f()));

            echo 1;

            $readyStrands = (yield Recoil::select([$strand]));

            echo 3;

            $this->assertSame([$strand], $readyStrands);
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }
}

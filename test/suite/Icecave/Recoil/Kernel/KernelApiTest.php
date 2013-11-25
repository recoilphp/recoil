<?php
namespace Icecave\Recoil\Kernel;

use Exception;
use Icecave\Recoil\Kernel\Exception\TimeoutException;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;

class KernelApiTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel;
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

    public function testReturnAndResume()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            $f = function () {
                yield Recoil::returnAndResume(1);
                echo 2;
            };

            echo (yield $f());
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testThrowAndResume()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            $f = function () {
                yield Recoil::throwAndResume(new Exception(1));
                echo 2;
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

    public function testTimeoutWithException()
    {
        $this->expectOutputString('1');

        $immediate = function () {
            yield Recoil::throw_(new Exception(1));
        };

        $coroutine = function () use ($immediate) {
            try {
                yield Recoil::timeout(0.01, $immediate());
            } catch (Exception $e) {
                echo $e->getMessage();
            }
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
}

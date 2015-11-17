<?php

namespace Recoil\Kernel\Api;

use Exception;
use PHPUnit_Framework_TestCase;
use Recoil\Kernel\Exception\TimeoutException;
use Recoil\Kernel\StandardKernel;
use Recoil\Recoil;

class TimeoutTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new StandardKernel();
    }

    public function testTimeout()
    {
        $this->expectOutputString('1');

        $immediate = function () {
            yield Recoil::return_(1);
        };

        $coroutine = function () use ($immediate) {
            echo(yield new Timeout(0.01, $immediate()));
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
                yield new Timeout(0.01, $immediate());
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testTimeoutExceeded()
    {
        $this->expectOutputString('123');

        $forever = function () {
            $f = function () {
                echo 2;
                yield Recoil::suspend(function () {});
                echo 'X';
            };

            echo 1;
            yield $f();
            echo 'X';
        };

        $coroutine = function () use ($forever) {
            try {
                yield new Timeout(0.01, $forever());
            } catch (TimeoutException $e) {
                echo 3;
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testTimerIsStoppedWhenStrandIsTerminated()
    {
        $this->expectOutputString('');

        $immediate = function () {
            yield Recoil::terminate();
        };

        $coroutine = function () use ($immediate) {
            yield Recoil::timeout(1, $immediate());
            echo 'X';
        };

        $strand = $this->kernel->execute($coroutine());

        $start = microtime(true);
        $this->kernel->eventLoop()->run();
        $end = microtime(true);

        $this->assertLessThan(1, $end - $start);
    }
}

<?php

namespace Recoil\Kernel\Api;

use Exception;
use PHPUnit_Framework_TestCase;
use Recoil\Kernel\Exception\TimeoutException;
use Recoil\Kernel\StandardKernel;
use Recoil\Kernel\Strand\Strand;
use Recoil\Recoil;

/**
 * @covers Recoil\Kernel\Api\StandardKernelApi
 * @covers Recoil\Kernel\Api\KernelApiCall
 */
class StandardKernelApiTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel    = new StandardKernel();
        $this->tolerance = 0.02;
    }

    public function testStrand()
    {
        $strand    = null;
        $coroutine = function () use (&$strand) {
            $strand = (yield Recoil::strand());
        };

        $expectedStrand = $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertSame($expectedStrand, $strand);
    }

    public function testKernel()
    {
        $kernel    = null;
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

            echo(yield $f());
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

    public function testFinally()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            $strand    = (yield Recoil::strand());
            $coroutine = $strand->current();

            yield Recoil::finally_(
                function ($s, $c) use ($strand, $coroutine) {
                    $this->assertSame($strand, $s);
                    $this->assertSame($coroutine, $c);
                    echo 2;
                }
            );

            echo 1;
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testFinallyWithException()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            yield Recoil::finally_(
                function () {
                    echo 2;
                }
            );

            echo 1;

            throw new Exception('This is the exception.');
        };

        $this->kernel->execute($coroutine());

        $this->setExpectedException('Exception', 'This is the exception.');

        $this->kernel->eventLoop()->run();
    }

    public function testFinallyWithTermination()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            yield Recoil::finally_(
                function () {
                    echo 2;
                }
            );

            echo 1;

            yield Recoil::terminate();
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
        $end   = 0;

        $coroutine = function () use (&$start, &$end) {
            $start = microtime(true);
            yield Recoil::sleep(0.15);
            $end = microtime(true);
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertEquals(0.15, $end - $start, '', $this->tolerance);
    }

    public function testSuspend()
    {
        $this->expectOutputString('');

        $coroutine = function () {
            yield Recoil::suspend();
            echo 'X';
        };

        $expectedStrand = $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testSuspendWithCallback()
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
            echo(yield Recoil::timeout(0.01, $immediate()));
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testAll()
    {
        $this->expectOutputString('12345');

        $result    = null;
        $coroutine = function () use (&$result) {
            $f = function ($value) {
                echo $value;
                yield Recoil::return_($value * 2);
            };

            echo 1;

            $result = (yield Recoil::all([
                $f(2),
                $f(3),
                $f(4),
            ]));

            echo 5;
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertSame([4, 6, 8], $result);
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

        $strand    = null;
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

        $this->assertInstanceOf(Strand::class, $strand);
    }

    public function testCallback()
    {
        $this->expectOutputString('123');

        $coroutine = function () {
            $f = function () {
                echo 3;
                yield Recoil::noop();
            };

            echo 1;
            $callback = (yield Recoil::callback($f()));
            $callback();
            echo 2;
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testCallbackWithCallable()
    {
        $this->expectOutputString('123');

        $coroutine = function () {
            $f = function ($value) {
                echo $value;
                yield Recoil::noop();
            };

            echo 1;
            $callback = (yield Recoil::callback($f));
            $callback(3);
            echo 2;
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testStop()
    {
        $this->expectOutputString('1');

        $coroutine1 = function () {
            echo 1;
            yield Recoil::stop();
            echo 'X';
        };

        $coroutine2 = function () {
            yield Recoil::noop();
            echo 'X';
        };

        $this->kernel->execute($coroutine1());
        $this->kernel->execute($coroutine2());

        // Add a timer to ensure that stop also stops the event-loop ...
        $this->kernel->eventLoop()->addTimer(
            0.1,
            function () {
                echo 'X';
            }
        );

        $this->kernel->eventLoop()->run();
    }

    public function testStopKernelOnly()
    {
        $this->expectOutputString('12');

        $coroutine1 = function () {
            echo 1;
            yield Recoil::stop(false);
            echo 'X';
        };

        $coroutine2 = function () {
            yield Recoil::noop();
            echo 'X';
        };

        $this->kernel->execute($coroutine1());
        $this->kernel->execute($coroutine2());

        // Add a timer to ensure that stop also stops the event-loop ...
        $this->kernel->eventLoop()->addTimer(
            0.1,
            function () {
                echo '2';
            }
        );

        $this->kernel->eventLoop()->run();
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

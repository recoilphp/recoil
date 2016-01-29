<?php

declare (strict_types = 1);

namespace Recoil;

use Eloquent\Phony\Phpunit\Phony;
use Exception;
use Generator;
use Recoil\Kernel\Api;
use Recoil\Kernel\Awaitable;
use Recoil\Kernel\AwaitableProvider;
use Recoil\Kernel\CoroutineProvider;
use Recoil\Kernel\Strand;

trait FunctionalApiTestTrait
{
    public function asyncTestExecute()
    {
        $spy = Phony::spy(
            function () {
                return '<ok>';
                yield;
            }
        );

        $strand = yield Recoil::execute($spy);

        $this->assertInstanceOf(
            Strand::class,
            $strand
        );

        $spy->never()->called();

        $this->assertSame(
            '<ok>',
            yield $strand
        );
    }

    public function asyncTestCallback()
    {
        $spy = Phony::spy(
            function () {
                return;
                yield;
            }
        );

        $callback = yield Recoil::callback($spy);
        $this->assertTrue(is_callable($callback));

        $spy->never()->called();

        $callback();

        $spy->never()->called();

        yield;

        $spy->called();
    }

    public function asyncTestCooperate()
    {
        $this->expectOutputString('123');

        yield Recoil::execute(function () {
            echo 2;
            return;
            yield;
        });

        echo 1;
        yield;
        echo 3;
    }

    public function asyncTestSleep()
    {
        $time = microtime(true);
        yield 0.1;
        $diff = microtime(true) - $time;

        $this->assertEquals(
            0.1,
            $diff,
            '',
            0.05
        );
    }

    public function asyncTestSleepWithExplicitCall()
    {
        $time = microtime(true);
        yield Recoil::sleep(0.1);
        $diff = microtime(true) - $time;

        $this->assertEquals(
            0.1,
            $diff,
            '',
            0.05
        );
    }

    public function asyncTestTimeout()
    {
        $this->assertEquals(
            '<ok>',
            yield Recoil::timeout(
                0.1,
                function () {
                    return '<ok>';
                    yield;
                }
            )
        );
    }
}

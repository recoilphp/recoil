<?php

declare (strict_types = 1);

namespace Recoil;

use Exception;
use Generator;
use Recoil\Kernel\Api;
use Recoil\Kernel\Awaitable;
use Recoil\Kernel\AwaitableProvider;
use Recoil\Kernel\CoroutineProvider;
use Recoil\Kernel\Strand;

trait FunctionalInvokeTestTrait
{
    public function asyncTestInvokeCoroutine()
    {
        $this->expectOutputString('123');

        $fn = function () {
            echo 2;
            return 3;
            yield;
        };

        echo 1;
        echo yield $fn();
    }

    public function asyncTestInvokeCoroutineWithYieldFrom()
    {
        $this->expectOutputString('123');

        $fn = function () {
            echo 2;
            return 3;
            yield;
        };

        echo 1;
        echo yield from $fn();
    }

    public function asyncTestInvokeCoroutineProvider()
    {
        $this->expectOutputString('123');

        echo 1;
        echo yield new class implements CoroutineProvider
        {
            public function coroutine() : Generator
            {
                echo 2;
                return 3;
                yield;
            }
        };
    }

    public function asyncTestInvokeAwaitable()
    {
        $this->expectOutputString('12');

        echo 1;
        echo yield new class implements Awaitable
        {
            public function await(Strand $strand, Api $api)
            {
                $strand->resume(2);
            }
        };
    }

    public function asyncTestInvokeAwaitableProvider()
    {
        $this->expectOutputString('12');

        echo 1;
        echo yield new class implements AwaitableProvider
        {
            public function awaitable() : Awaitable
            {
                return new class implements Awaitable
                {
                    public function await(Strand $strand, Api $api)
                    {
                        $strand->resume(2);
                    }
                };
            }
        };
    }

    public function asyncTestExceptionPropagatesUpCallStack()
    {
        $this->setExpectedException(
            Exception::class,
            '<exception>'
        );

        $fn = function () {
            throw new Exception('<exception>');
            yield;
        };

        echo yield $fn();
    }
}

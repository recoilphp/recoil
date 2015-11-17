<?php

namespace Recoil\Kernel\Api;

use Exception;
use PHPUnit_Framework_TestCase;
use Recoil\Kernel\Exception\StrandTerminatedException;
use Recoil\Recoil;

class WaitAllTest extends PHPUnit_Framework_TestCase
{
    public function testWaitAll()
    {
        $this->expectOutputString('321');

        Recoil::run(
            function () {
                $f = function ($value, $sleepTime) {
                    yield Recoil::sleep($sleepTime);
                    echo $value;
                    yield Recoil::return_($value * 2);
                };

                $result = (yield Recoil::all([
                    $f(1, 0.2),
                    $f(2, 0.1),
                    $f(3, 0.0),
                ]));

                $this->assertSame([2, 4, 6], $result);
            }
        );
    }

    public function testWaitAllWithFailure()
    {
        Recoil::run(
            function () {
                $f = function ($fail) {
                    if ($fail) {
                        throw new Exception('This is the exception.');
                    }

                    yield Recoil::sleep(1);
                    echo 'X';
                };

                try {
                    yield Recoil::all([
                        $f(false),
                        $f(true),
                    ]);

                // Do not allow the exception to propagate to verify that the non-failed
                // coroutine is terminated (the "X" is never echoed).
                } catch (Exception $e) {
                    $this->assertSame('This is the exception.', $e->getMessage());

                    return;
                }

                $this->fail('Expected exception was not thrown.');
            }
        );
    }

    public function testWaitAllWithTermination()
    {
        Recoil::run(
            function () {
                $f = function ($terminate) {
                    if ($terminate) {
                        yield Recoil::terminate();
                    }

                    yield Recoil::sleep(1);
                    echo 'X';
                };

                try {
                    yield Recoil::all([
                        $f(false),
                        $f(true),
                    ]);

                // Do not allow the exception to propagate to verify that the non-failed
                // coroutine is terminated (the "X" is never echoed).
                } catch (Exception $e) {
                    $this->assertInstanceOf(StrandTerminatedException::class, $e);

                    return;
                }

                $this->fail('Expected exception was not thrown.');
            }
        );
    }

    public function testWaitAllWhenSuspendedStrandIsTerminated()
    {
        $this->expectOutputString('1');

        Recoil::run(
            function () {
                $f = function () {
                    $f = function () {
                        yield Recoil::sleep(0.1);
                        echo 'X';
                    };

                    echo 1;
                    yield Recoil::all([$f()]);
                    echo 'X';
                };

                $strand = (yield Recoil::execute($f()));

                yield Recoil::cooperate();

                $strand->terminate();
            }
        );
    }
}

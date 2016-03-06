<?php

namespace Recoil\Kernel\Api;

use PHPUnit_Framework_TestCase;
use Recoil\Kernel\StandardKernel;
use Recoil\Recoil;

class SelectTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new StandardKernel();
    }

    public function testSelect()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            $f = function ($id) {
                echo $id;
                yield Recoil::noop();
            };

            $strands = [
                'foo' => (yield Recoil::execute($f(1))),
                'bar' => (yield Recoil::execute($f(2))),
            ];

            $readyStrands = (yield Recoil::select($strands));

            $this->assertSame($strands, $readyStrands);
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testSelectAlreadyFinished()
    {
        $this->expectOutputString('12');

        $coroutine = function () {
            $f = function ($id) {
                echo 1;
                yield Recoil::noop();
            };

            $strand = (yield Recoil::execute($f(1)));

            yield Recoil::cooperate();

            echo 2;

            $readyStrands = (yield Recoil::select([$strand]));

            $this->assertSame([$strand], $readyStrands);
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }

    public function testSelectWhenSuspendedStrandIsTerminated()
    {
        $this->expectOutputString('1234');

        $coroutine = function () {
            $f = function () {
                echo 4;
                yield Recoil::sleep(0.25);
            };

            echo 1;

            $strand = (yield Recoil::execute($f()));

            echo 2;

            yield Recoil::select([$strand]);

            echo 'X';
        };

        $strand = $this->kernel->execute($coroutine());

        $terminator = function ($strand) {
            echo 3;
            $strand->terminate();
            yield Recoil::noop();
        };

        $this->kernel->execute($terminator($strand));

        $this->kernel->eventLoop()->run();
    }
}

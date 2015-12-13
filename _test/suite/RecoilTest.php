<?php

namespace Recoil;

use PHPUnit_Framework_TestCase;
use React\EventLoop\StreamSelectLoop;

class RecoilTest extends PHPUnit_Framework_TestCase
{
    public function testRun()
    {
        $this->expectOutputString('test');

        Recoil::run(
            function () {
                echo 'test';
                yield;
            }
        );
    }

    public function testRunWithExplicitEventLoop()
    {
        $this->expectOutputString('test');

        Recoil::run(
            function () {
                echo 'test';
                yield;
            },
            new StreamSelectLoop()
        );
    }
}

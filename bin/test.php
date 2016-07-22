<?php

declare (strict_types = 1);

namespace Foo;

require __DIR__ . '/../vendor/autoload.php';

use Generator as Chump;
use Recoil\React\ReactKernel;
use Recoil\Recoil;

/**
 * @recoil-coroutine
 */
function coro() : \Generator
{
    echo 'Sleeping... ';
    yield Recoil::sleep(1);
}

/**
 * @recoil-coroutine
 */
function foo() : Chump
{
    yield;
    yield from coro();
    echo 'DONE', PHP_EOL;
}

ReactKernel::start(foo());

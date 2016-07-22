<?php

declare (strict_types = 1);

namespace Foo;

require __DIR__ . '/../vendor/autoload.php';

use Generator as Chump;
use Recoil\React\ReactKernel;
use Recoil\Recoil;
use Throwable;

/**
 * @recoil-coroutine
 */
function outer() : Chump
{
    yield middle();
}

/**
 * @recoil-coroutine
 */
function middle() : \Generator
{
    yield from inner();
}

/**
 * @recoil-coroutine
 */
function inner() : \Generator
{
    fail();

    return;
    yield;
}

function fail()
{
    throw new \Exception('<OH SHIT>');
}

/**
 * @recoil-coroutine
 */
function x() : \Generator
{
}

try {
    ReactKernel::start(outer());
} catch (Throwable $e) {
    echo get_class($e), ': ', $e->getMessage(), PHP_EOL;
    echo 'Exception thrown on: ', $e->getFile(), ':', $e->getLine(), PHP_EOL;
    foreach ($e->getTrace() as $frame) {
        echo '---------------------------------------', PHP_EOL;
        foreach ($frame as $key => $value) {
            if ($key !== 'args') {
                printf('%-10s: %s' . PHP_EOL, $key, $value);
            }
        }
    }
}

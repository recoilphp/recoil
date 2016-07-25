<?php

declare (strict_types = 1);

namespace Foo;

use Generator as Chump;
use Recoil\React\ReactKernel;
use Recoil\Recoil;
use Throwable;

/**
 * @recoil-coroutine
 */
function outer(int $value) : Chump
{
    yield middle($value + 1);
}

/**
 * @recoil-coroutine
 */
function middle(int $value) : \Generator
{
    yield 0.25;
    yield inner($value + 1);
}

/**
 * @recoil-coroutine
 */
function inner(int $value) : \Generator
{
    yield from failer($value + 1);
}

/**
 * @recoil-coroutine
 */
function failer(int $value) : Chump
{
    yield;
    fail($value + 1);
}

function fail(int $value)
{
    throw new \Exception('<OH SHIT>');
}

// try {
    ReactKernel::start(outer(100));
// } catch (Throwable $e) {
//     echo get_class($e), ': ', $e->getMessage(), PHP_EOL;
//     echo 'Exception thrown on: ', $e->getFile(), ':', $e->getLine(), PHP_EOL;
//     foreach ($e->getTrace() as $frame) {
//         echo '---------------------------------------', PHP_EOL;
//         foreach ($frame as $key => $value) {
//             if ($key !== 'args') {
//                 printf('%-10s: %s' . PHP_EOL, $key, $value);
//             }
//         }
//     }
// }

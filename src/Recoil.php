<?php

declare (strict_types = 1);

namespace Recoil;

use Exception;
use Recoil\Kernel\Api;
use Recoil\Kernel\ApiCall;
use Recoil\Kernel\Awaitable;

/**
 * Public facade for kernel API calls.
 *
 * This class contains no implementation; it is a proxy for the kernel API
 * implementation provided by whichever coroutine kernel is currently being
 * used for execution.
 *
 * The standard API provides the following operations:
 *
 * @method static execute($task)                 Execute a task in a new strand.
 * @method static callback($task)                Create a callback that executes a task in a new strand.
 * @method static cooperate()                    Suspend execution briefly, allowing other strands to execute.
 * @method static sleep(float $seconds)          Suspend execution for a specified interval.
 * @method static timeout(float $seconds, $task) Execute a task with a maximum running time.
 * @method static terminate()                    Terminate the current strand.
 * @method static all(...$tasks)                 Execute tasks in parallel and wait for them all to complete.
 * @method static any(...$tasks)                 Execute tasks in parallel and wait for one of them to complete.
 * @method static some(int $count, ...$tasks)    Execute tasks in parallel and wait for a specific number of them to complete.
 * @method static race(...$tasks)                Execute tasks in parallel and wait for one of them to complete or produce an exception.
 */
abstract class Recoil
{
    /**
     * Invoke a kernel API operation.
     *
     * @see Api
     *
     * @param string $name      The operation name, corresponds to the methods in Api.
     * @param array  $arguments The operation arguments.
     */
    public static function __callStatic(string $name, array $arguments) : Awaitable
    {
        return new ApiCall($name, $arguments);
    }
}

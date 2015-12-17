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
 * @todo document API methods
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
    public static function __callStatic(string $name, array $arguments) : ApiCall
    {
        return new ApiCall($name, $arguments);
    }
}

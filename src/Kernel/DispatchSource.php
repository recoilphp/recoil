<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

/**
 * The source of a call to Api::__dispatch(). Can be used by the API to provide
 * different adaptation rules in different contexts.
 */
final class DispatchSource
{
    /**
     * __dispatch() is being invoked by the kernel.
     */
    const KERNEL = 1;

    /**
     * __dispatch() is being invoked from within the API.
     */
    const API = 2;

    /**
     * __dispatch() is being invoked because a coroutine suspended execution.
     */
    const COROUTINE = 3;

    /**
     * Prevent construction.
     */
    private function __construct()
    {
    }
}

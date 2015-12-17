<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Generator;
use Throwable;

interface Strand
{
    /**
     * Start the strand.
     *
     * @param Generator|callable $coroutine The strand's entry-point.
     */
    public function start($coroutine);

    /**
     * Terminate execution of the strand.
     *
     * If the strand is suspended waiting on an asynchronous operation, that
     * operation is cancelled.
     *
     * The call stack is not unwound, it is simply discarded.
     */
    public function terminate();

    /**
     * Resume execution of a suspended strand.
     *
     * @param mixed $value The value to send to the coroutine on the the top of the call stack.
     */
    public function resume($value = null);

    /**
     * Resume execution of a suspended strand with an error.
     *
     * @param Throwable $exception The exception to send to the coroutine on the top of the call stack.
     */
    public function throw(Throwable $exception);
}

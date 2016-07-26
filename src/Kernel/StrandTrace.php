<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

/**
 * A strand trace is a low-level observer of strand events.
 *
 * @see Strand::setTrace()
 *
 * A trace may only be set on a strand when assertions are enabled. When
 * assertions are disabled, all tracing related code is disabled, and setting
 * a trace has no effect.
 *
 * If an exception is thrown from any of the StrandTrace methods the kernel
 * behaviour is undefined.
 */
interface StrandTrace
{
    /**
     * Record a push to the call-stack.
     *
     * @param $depth The depth of the stack BEFORE the push operation.
     *
     * @return null
     */
    public function push(Strand $strand, int $depth);

    /**
     * Record a pop from the call-stack.
     *
     * @param $depth The depth of the stack AFTER the pop operation.
     *
     * @return null
     */
    public function pop(Strand $strand, int $depth);

    /**
     * Record values yielded from the coroutine on the head of the stack.
     *
     * @return null
     */
    public function yield(Strand $strand, $key, $value);

    /**
     * Record the action and value used to resume a yielded coroutine.
     *
     * @return null
     */
    public function resume(Strand $strand, string $action, $value);

    /**
     * Record the suspension of a strand.
     *
     * @return null
     */
    public function suspend(Strand $strand);

    /**
     * Record the action and value when a strand exits.
     *
     * @return null
     */
    public function exit(Strand $strand, string $action, $value);
}

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
 */
interface StrandTrace
{
    /**
     * Record a push to the call stack.
     *
     * @return null
     */
    public function push(Strand $strand, int $depth);

    /**
     * Record a pop from the call stack.
     *
     * @return null
     */
    public function pop(Strand $strand);

    /**
     * Record values yielded from the coroutine on the head of the stack.
     *
     * The value may be modified.
     *
     * @return null
     */
    public function yield(Strand $strand, $key, &$value);

    /**
     * Record the action and value used to resume a yielded coroutine.
     *
     * The action and value may be modified.
     *
     * @return null
     */
    public function resume(Strand $strand, string &$action, &$value);

    /**
     * Record the return value from the coroutine on the head of the stack.
     *
     * The value may be modified.
     *
     * @return null
     */
    public function return(Strand $strand, &$value);

    /**
     * Record the suspension of a strand.
     *
     * @return null
     */
    public function suspend(Strand $strand);

    /**
     * Record the action and value when a strand exits.
     *
     * The action and value may be modified.
     *
     * @return null
     */
    public function exit(Strand $strand, string &$action, &$value);
}

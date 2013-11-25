<?php
namespace Icecave\Recoil\Kernel;

use Exception;

/**
 * Public interface for manipulating the kernel and the current strand.
 */
interface KernelApiInterface
{
    /**
     * Return a value to the calling co-routine and continue executing.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to send to the calling co-routine.
     */
    public function return_(StrandInterface $strand, $value = null);

    /**
     * Throw an exception to the calling co-routine and continue executing.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param Exception       $exception The error to send to the calling co-routine.
     */
    public function throw_(StrandInterface $strand, Exception $exception);

    /**
     * Terminate execution of the strand.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function terminate(StrandInterface $strand);

    /**
     * Suspend execution for a specified period of time.
     *
     * @param StrandInterface $strand  The currently executing strand.
     * @param number          $timeout The number of seconds to wait before resuming.
     */
    public function sleep(StrandInterface $strand, $timeout);

    /**
     * Suspend execution of the strand until it is resumed manually.
     *
     * @param StrandInterface $strand   The currently executing strand.
     * @param callable        $callback A callback which is passed the strand after it is suspended.
     */
    public function suspend(StrandInterface $strand, callable $callback);

    /**
     * Resume the strand immediately.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function noop(StrandInterface $strand);

    /**
     * Suspend the strand until the next tick.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function cooperate(StrandInterface $strand);
}

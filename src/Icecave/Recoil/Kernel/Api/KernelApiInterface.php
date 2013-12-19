<?php
namespace Icecave\Recoil\Kernel\Api;

use Exception;
use Icecave\Recoil\Kernel\Strand\StrandInterface;

/**
 * Public interface for manipulating the kernel and the current strand.
 */
interface KernelApiInterface
{
    /**
     * Get the strand the co-routine is executing on.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function strand(StrandInterface $strand);

    /**
     * Get the co-routine kernel that the current strand is executing on.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function kernel(StrandInterface $strand);

    /**
     * Get the ReactPHP event-loop that the co-routine kernel is executing on.
     *
     * @param StrandInterface $strand The currently executing strand.
     */
    public function eventLoop(StrandInterface $strand);

    /**
     * Return a value to the calling co-routine.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to send to the calling co-routine.
     */
    public function return_(StrandInterface $strand, $value = null);

    /**
     * Throw an exception to the calling co-routine.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param Exception       $exception The error to send to the calling co-routine.
     */
    public function throw_(StrandInterface $strand, Exception $exception);

    /**
     * Return a value to the calling co-routine and continue executing.
     *
     * @param StrandInterface $strand The currently executing strand.
     * @param mixed           $value  The value to send to the calling co-routine.
     */
    public function returnAndResume(StrandInterface $strand, $value = null);

    /**
     * Throw an exception to the calling co-routine and continue executing.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param Exception       $exception The error to send to the calling co-routine.
     */
    public function throwAndResume(StrandInterface $strand, Exception $exception);

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
     * Execute a co-routine with a time limit.
     *
     * If the co-routine does not complete within the specified time it is
     * cancelled.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param number          $timeout   The number of seconds to wait before cancelling.
     * @param mixed           $coroutine The coroutine to execute.
     */
    public function timeout(StrandInterface $strand, $timeout, $coroutine);

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

    /**
     * Execute a co-routine on its own strand.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param mixed           $coroutine The co-routine to execute.
     */
    public function execute(StrandInterface $strand, $coroutine);

    /**
     * Wait for one or more of the given strands to exit.
     *
     * @param StrandInterface        $strand  The currently executing strand.
     * @param array<StrandInterface> $strands The strands to wait for.
     */
    public function select(StrandInterface $strand, array $strands);
}

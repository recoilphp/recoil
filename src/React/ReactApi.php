<?php

declare (strict_types = 1);

namespace Recoil\React;

use React\EventLoop\LoopInterface;
use Recoil\Kernel\Api;
use Recoil\Kernel\ApiTrait;
use Recoil\Kernel\DispatchSource;
use Recoil\Kernel\Strand;
use Recoil\Kernel\Suspendable;

/**
 * A kernel API based on the React event loop.
 */
final class ReactApi implements Api
{
    /**
     * @param LoopInterface $eventLoop The event loop.
     */
    public function __construct(LoopInterface $eventLoop)
    {
        $this->eventLoop = $eventLoop;
    }

    /**
     * Start a new strand of execution.
     *
     * This method executes a task in the "background". The caller is resumed
     * with the {@see Strand} before the strand is started.
     *
     * @param Strand      $strand The strand the caller is executing on.
     * @param Suspendable $caller The object waiting for the task to complete.
     * @param mixed       $task   The task to execute.
     */
    public function execute(Strand $strand, Suspendable $caller, $task)
    {
        $substrand = new ReactStrand();

        $this->eventLoop->futureTick(
            function () use ($substrand, $task) {
                $this->__dispatch(
                    DispatchSource::API,
                    $substrand,
                    $substrand,
                    $task
                );
            }
        );

        $caller->resume($substrand);
    }

    /**
     * Create a callback function that starts a new strand of execution.
     *
     * This method can be used to integrate the kernel with callback-based
     * asynchronous code.
     *
     * The caller is resumed with the callback.
     *
     * @param Strand      $strand The strand the caller is executing on.
     * @param Suspendable $caller The object waiting for the task to complete.
     * @param mixed       $task   The task to execute.
     */
    public function callback(Strand $strand, Suspendable $caller, $task)
    {
        $caller->resume(
            function () use ($task) {
                $substrand = new ReactStrand();

                return $this->__dispatch(
                    DispatchSource::API,
                    $substrand,
                    $substrand,
                    $task
                );
            }
        );
    }

    /**
     * Allow other strands to execute then resume The object waiting for the task to complete.
     *
     * @param Strand      $strand The strand the caller is executing on.
     * @param Suspendable $caller The object waiting for the task to complete.
     */
    public function cooperate(Strand $strand, Suspendable $caller)
    {
        $this->eventLoop->futureTick(
            function () use ($caller) {
                $caller->resume();
            }
        );
    }

    /**
     * Resume execution of the caller after a specified interval.
     *
     * @param Strand      $strand  The strand the caller is executing on.
     * @param Suspendable $caller  The object waiting for the task to complete.
     * @param float       $seconds The interval to wait.
     */
    public function sleep(Strand $strand, Suspendable $caller, float $seconds)
    {
        if ($seconds <= 0) {
            $this->eventLoop->futureTick(
                function () use ($caller) {
                    $caller->resume();
                }
            );
        }

        // @todo handle cancel
        $this->eventLoop->addTimer(
            $seconds,
            function () use ($caller) {
                $caller->resume();
            }
        );
    }

    /**
     * Execute a task with a maximum running time.
     *
     * If the task does not complete within the specified time it is cancelled,
     * otherwise the caller is resumed with the value or exception produced.
     *
     * @param Strand      $strand  The strand the caller is executing on.
     * @param Suspendable $caller  The object waiting for the task to complete.
     * @param float       $seconds The interval to allow for execution.
     * @param mixed       $task    The task to execute.
     */
    public function timeout(Strand $strand, Suspendable $caller, float $seconds, $task)
    {
        $current->throw(new \LogicException('Not implemented.'));
    }

    /**
     * Get the event loop.
     *
     * The caller is resumed with the event loop used by this API.
     *
     * @param Strand      $strand The strand the caller is executing on.
     * @param Suspendable $caller The object waiting for the task to complete.
     */
    public function eventLoop(Strand $strand, Suspendable $caller)
    {
        $caller->resume($this->eventLoop);
    }

    use ApiTrait;

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;
}

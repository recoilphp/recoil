<?php

declare (strict_types = 1);

namespace Recoil\React;

use React\EventLoop\LoopInterface;
use Recoil\Kernel\Api;
use Recoil\Kernel\ApiTrait;
use Recoil\Kernel\DispatchSource;
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
     * @param Suspendable $caller The object waiting for the task to complete.
     * @param mixed       $task   The task to execute.
     */
    public function execute(Suspendable $caller, $task)
    {
        $strand = new ReactStrand();

        $this->eventLoop->futureTick(
            function () use ($strand, $task) {
                $this->__dispatch(
                    DispatchSource::API,
                    $strand,
                    $task
                );
            }
        );

        $caller->resume($strand);
    }

    /**
     * Allow other strands to execute then resume The object waiting for the task to complete.
     *
     * @param Suspendable $caller The object waiting for the task to complete.
     */
    public function cooperate(Suspendable $caller)
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
     * @param Suspendable $caller  The object waiting for the task to complete.
     * @param float       $seconds The interval to wait.
     */
    public function sleep(Suspendable $caller, float $seconds)
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
     * @param Suspendable $caller  The object waiting for the task to complete.
     * @param float       $seconds The interval to allow for execution.
     * @param mixed       $task    The task to execute.
     */
    public function timeout(Suspendable $caller, float $seconds, $task)
    {
        $current->throw(new \LogicException('Not implemented.'));
    }

    /**
     * Get the event loop.
     *
     * The caller is resumed with the event loop used by this API.
     *
     * @param Suspendable $caller The object waiting for the task to complete.
     */
    public function eventLoop(Suspendable $caller)
    {
        $caller->resume($this->eventLoop);
    }

    use ApiTrait;

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;
}

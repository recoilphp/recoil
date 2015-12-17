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
     * This method executes a coroutine in a new strand. The calling strand is
     * resumed with the new {@see Strand} object.
     *
     * The coroutine can be a generator object, or a generator function.
     *
     * The implementation must delay execution of the strand until the next
     * 'tick' of the kernel to allow the user to inspect the strand object
     * before execution begins.
     *
     * @param Strand $strand The strand executing the API call.
     * @param Generator|callable $coroutine The coroutine to execute.
     */
    public function execute(Strand $strand, $coroutine)
    {
        $substrand = new ReactStrand($this);

        $this->eventLoop->futureTick(
            static function () use ($substrand, $coroutine) {
                $substrand->start($coroutine);
            }
        );

        $strand->resume($substrand);
    }

    /**
     * Create a callback function that starts a new strand of execution.
     *
     * This method can be used to integrate the kernel with callback-based
     * asynchronous code.
     *
     * The coroutine can be a generator object, or a generator function.
     *
     * The caller is resumed with the callback.
     *
     * @param Strand $strand The strand executing the API call.
     * @param Generator|callable $coroutine The coroutine to execute.
     */
    public function callback(Strand $strand, $coroutine)
    {
        $substrand = new ReactStrand($this);

        $strand->resume(
            static function () use ($substrand, $coroutine) {
                $substrand->start($coroutine);
            }
        );
    }

    /**
     * Allow other strands to execute then resume the strand.
     *
     * @param Strand $strand The strand executing the API call.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function cooperate(Strand $strand)
    {
        $this->eventLoop->futureTick(
            static function () use ($strand) {
                $strand->resume();
            }
        );
    }

    /**
     * Resume execution of the strand after a specified interval.
     *
     * @param Strand $strand  The strand executing the API call.
     * @param float  $seconds The interval to wait.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function sleep(Strand $strand, float $seconds)
    {
        if ($seconds >= 0) {
            $timer = $this->eventLoop->addTimer(
                $seconds,
                static function () use ($strand) {
                    $strand->resume();
                }
            );

            return static function () use ($strand) {
                $strand->cancel();
            };
        }

        $this->eventLoop->futureTick(
            static function () use ($strand) {
                $strand->resume();
            }
        );
    }

    /**
     * Execute a task on its own strand that is terminated after a timeout.
     *
     * If the task does not complete within the specific time its strand is
     * terminated and the calling strand is resumed with a
     * {@see TimeoutException}. Otherwise, the calling strand is resumed with
     * the value or exception produced by the task.
     *
     * The task can be a generator object, a generator function, or any value
     * that can be used with __dispatch().
     *
     * @param Strand $strand  The strand executing the API call.
     * @param float  $seconds The interval to allow for execution.
     * @param mixed  $task    The task to execute.
     *
     * @return callable|null A callable that cancels the operation.
     */
    public function timeout(Strand $strand, float $seconds, $task)
    {
        $current->throw(new \LogicException('Not implemented.'));
    }

    /**
     * Get the event loop.
     *
     * The caller is resumed with the event loop used by this API.
     *
     * @param Strand $strand  The strand executing the API call.
     */
    public function eventLoop(Strand $strand)
    {
        $strand->resume($this->eventLoop);
    }

    use ApiTrait;

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;
}

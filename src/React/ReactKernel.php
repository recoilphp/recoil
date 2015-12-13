<?php

declare (strict_types = 1);

namespace Recoil\React;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Recoil\Kernel\Api;
use Recoil\Kernel\DispatchSource;
use Recoil\Kernel\Kernel;
use Recoil\Kernel\Strand;
use RuntimeException;

/**
 * A Recoil coroutine kernel based on a ReactPHP event loop.
 */
final class ReactKernel implements Kernel
{
    /**
     * Execute a task on a new kernel.
     *
     * This method blocks until the all work on the kernel is complete.
     *
     * @param mixed              $task      The task to execute.
     * @param LoopInterface|null $eventLoop The event loop to use (null = default).
     *
     * @return mixed The result of the task.
     */
    public static function start($task, LoopInterface $eventLoop = null)
    {
        $kernel = new self($eventLoop);
        $strand = $kernel->execute($task);

        $resolved = false;
        $result = null;

        $strand->capture()->done(
            function ($value) use (&$resolved, &$result) {
                $resolved = true;
                $result = $value;
            }
        );

        $kernel->eventLoop->run();

        if (!$resolved) {
            throw new RuntimeException('The task did not complete.');
        }

        return $result;
    }

    /**
     * @param LoopInterface $eventLoop The event loop.
     * @param Api           $api       The kernel API.
     */
    public function __construct(LoopInterface $eventLoop = null, Api $api = null)
    {
        $this->eventLoop = $eventLoop ?: Factory::create();
        $this->api = $api ?: new ReactApi($this->eventLoop);
    }

    /**
     * Start a new strand of execution.
     *
     * The task can be any value that is accepted by the API's __dispatch()
     * method.
     *
     * The kernel implementation must delay execution of the strand until the
     * next tick, allowing the caller to use Strand::capture() if necessary.
     *
     * @param mixed $task The task to execute.
     *
     * @return Strand
     */
    public function execute($task) : Strand
    {
        $strand = new ReactStrand();

        $this->eventLoop->futureTick(
            function () use ($strand, $task) {
                $this->api->__dispatch(
                    DispatchSource::KERNEL,
                    $strand,
                    $task
                );
            }
        );

        return $strand;
    }

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;

    /**
     * @var Api The kernel API.
     */
    private $api;
}

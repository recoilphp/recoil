<?php
use Icecave\Recoil\Engine;

use React\EventLoop\LoopInterface;
use React\EventLoop\Factory;

class Engine
{
    public function __construct(LoopInterface $eventLoop)
    {
        $this->queue = new Queue;
    }

    public function execute($coroutine)
    {
        $continuation = new Continuation(new Root);
        $action = $continuation->call($coroutine);

        $this->queue->push(
            $continuation->call($coroutine)
        );
    }

    public function adapt($coroutine)
    {
        if ($coroutine instanceof CoroutineInterface) {
            return $coroutine;
        } elseif ($coroutine instanceof Generator) {
            return new GeneratorCoroutine($coroutine);
        }

        throw new InvalidArgumentException('Can not adapt ' . gettype($coroutine));
    }

    public function run()
    {
        $task = null;
        while ($this->queue->tryPop($task)) {
            if ($next = $task($this)) {
                $this->queue->push($next);
            }
        }
    }

    private $queue;
}

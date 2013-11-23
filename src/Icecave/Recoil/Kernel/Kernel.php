<?php
namespace Icecave\Recoil\Kernel;

use Icecave\Recoil\Coroutine\CoroutineAdaptor;
use Icecave\Recoil\Coroutine\CoroutineAdaptorInterface;
use React\EventLoop\Factory as EventLoopFactory;
use React\EventLoop\LoopInterface;
use SplQueue;

class Kernel implements KernelInterface
{
    public function __construct(
        KernelApiInterface $api = null,
        CoroutineAdaptorInterface $coroutineAdaptor = null,
        StrandFactoryInterface $strandFactory = null,
        LoopInterface $eventLoop = null
    ) {
        if (null === $api) {
            $api = new KernelApi;
        }

        if (null === $coroutineAdaptor) {
            $coroutineAdaptor = new CoroutineAdaptor;
        }

        if (null === $strandFactory) {
            $strandFactory = new StrandFactory;
        }

        if (null === $eventLoop) {
            $eventLoop = EventLoopFactory::create();
        }

        $this->api = $api;
        $this->coroutineAdaptor = $coroutineAdaptor;
        $this->strandFactory = $strandFactory;
        $this->eventLoop = $eventLoop;
        $this->strands = new SplQueue;
    }

    public function execute($coroutine)
    {
        $strand = $this->strandFactory()->createStrand($this);
        $strand->call($coroutine);
        $this->executeStrand($strand);
    }

    public function executeStrand(StrandInterface $strand)
    {
        if ($this->strands->isEmpty()) {
            $this->eventLoop()->nextTick(
                function () {
                    $this->tick();
                }
            );
        }

        $this->strands->push($strand);
    }

    public function api()
    {
        return $this->api;
    }

    public function coroutineAdaptor()
    {
        return $this->coroutineAdaptor;
    }

    public function strandFactory()
    {
        return $this->strandFactory;
    }

    public function eventLoop()
    {
        return $this->eventLoop;
    }

    protected function tick()
    {
        $strands = $this->strands;
        $this->strands = new SplQueue;

        while (!$strands->isEmpty()) {
            $strand = $strands->dequeue();

            if ($strand->tick()) {
                $this->executeStrand($strand);
            }
        }
    }

    private $api;
    private $coroutineAdaptor;
    private $strandFactory;
    private $eventLoop;
    private $strands;
}

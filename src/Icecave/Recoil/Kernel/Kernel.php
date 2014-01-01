<?php
namespace Icecave\Recoil\Kernel;

use Icecave\Recoil\Coroutine\CoroutineAdaptor;
use Icecave\Recoil\Coroutine\CoroutineAdaptorInterface;
use Icecave\Recoil\Kernel\Api\KernelApi;
use Icecave\Recoil\Kernel\Api\KernelApiInterface;
use Icecave\Recoil\Kernel\Strand\StrandFactory;
use Icecave\Recoil\Kernel\Strand\StrandFactoryInterface;
use Icecave\Recoil\Kernel\Strand\StrandInterface;
use React\EventLoop\Factory as EventLoopFactory;
use React\EventLoop\LoopInterface;
use SplObjectStorage;

/**
 * The default kernel implementation.
 */
class Kernel implements KernelInterface
{
    /**
     * @param LoopInterface|null             $eventLoop        The React event-loop.
     * @param KernelApiInterface|null        $api              The kernel's API implementation.
     * @param CoroutineAdaptorInterface|null $coroutineAdaptor The kernel's coroutine adaptor.
     * @param StrandFactoryInterface|null    $strandFactory    The kernel's strand factory.
     */
    public function __construct(
        LoopInterface $eventLoop = null,
        KernelApiInterface $api = null,
        CoroutineAdaptorInterface $coroutineAdaptor = null,
        StrandFactoryInterface $strandFactory = null
    ) {
        if (null === $eventLoop) {
            $eventLoop = EventLoopFactory::create();
        }

        if (null === $api) {
            $api = new KernelApi;
        }

        if (null === $coroutineAdaptor) {
            $coroutineAdaptor = new CoroutineAdaptor;
        }

        if (null === $strandFactory) {
            $strandFactory = new StrandFactory;
        }

        $this->eventLoop = $eventLoop;
        $this->api = $api;
        $this->coroutineAdaptor = $coroutineAdaptor;
        $this->strandFactory = $strandFactory;
        $this->strands = new SplObjectStorage;
    }

    /**
     * Execute a coroutine in a new strand of execution.
     *
     * The parameter may be any value that can be adapted into a coroutine by
     * the kernel's coroutine adaptor.
     *
     * @param mixed $coroutine The coroutine to execute.
     *
     * @return StrandInterface The strand on which the coroutine will execute.
     */
    public function execute($coroutine)
    {
        $strand = $this->strandFactory()->createStrand($this);
        $strand->call($coroutine);

        return $strand;
    }

    /**
     * Attach an existing strand to this kernel.
     *
     * @param StrandInterface The strand to attach.
     */
    public function attachStrand(StrandInterface $strand)
    {
        if (0 === $this->strands->count()) {
            $this->registerTick();
        }

        $this->strands->attach($strand);
    }

    /**
     * Detach an existing strand from this kernel.
     *
     * @param StrandInterface The strand to detach.
     */
    public function detachStrand(StrandInterface $strand)
    {
        $this->strands->detach($strand);
    }

    /**
     * Fetch the object that implements the kernel API.
     *
     * @return KernelApiInterface The kernel's API implementation.
     */
    public function api()
    {
        return $this->api;
    }

    /**
     * Fetch the object used to adapt values into coroutines.
     *
     * @return CoroutineAdaptorInterface The kernel's coroutine adaptor.
     */
    public function coroutineAdaptor()
    {
        return $this->coroutineAdaptor;
    }

    /**
     * Fetch the factory used to create new strands.
     *
     * @return StrandFactoryInterface The kernel's strand factory.
     */
    public function strandFactory()
    {
        return $this->strandFactory;
    }

    /**
     * Fetch the React event-loop.
     *
     * @return LoopInterface The React event-loop.
     */
    public function eventLoop()
    {
        return $this->eventLoop;
    }

    /**
     * Step each of the strands attached to this kernel.
     */
    protected function tick()
    {
        foreach (clone $this->strands as $strand) {
            $strand->tick();
        }

        if (0 !== $this->strands->count()) {
            $this->registerTick();
        }
    }

    protected function registerTick()
    {
        $this->eventLoop()->nextTick(
            function () {
                $this->tick();
            }
        );
    }

    private $eventLoop;
    private $api;
    private $coroutineAdaptor;
    private $strandFactory;
    private $strands;
}

<?php
namespace Icecave\Recoil\Kernel;

use Icecave\Recoil\Coroutine\CoroutineAdaptor;
use Icecave\Recoil\Coroutine\CoroutineAdaptorInterface;
use React\EventLoop\Factory as EventLoopFactory;
use React\EventLoop\LoopInterface;
use SplObjectStorage;

/**
 * The default kernel implementation.
 */
class Kernel implements KernelInterface
{
    /**
     * @param KernelApiInterface|null        $api              The kernel's API implementation.
     * @param CoroutineAdaptorInterface|null $coroutineAdaptor The kernel's co-routine adaptor.
     * @param StrandFactoryInterface|null    $strandFactory    The kernel's strand factory.
     * @param LoopInterface|null             $eventLoop        The ReactPHP event-loop.
     */
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
        $this->strands = new SplObjectStorage;
    }

    /**
     * Execute a co-routine in a new strand of execution.
     *
     * The parameter may be any value that can be adapted into a co-routine by
     * the kernel's co-routine adaptor.
     *
     * @param mixed $coroutine The co-routine to execute.
     *
     * @return StrandInterface The strand on which the co-routine will execute.
     */
    public function execute($coroutine)
    {
        $strand = $this->strandFactory()->createStrand($this);
        $strand->call($coroutine);

        $this->attachStrand($strand);

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
     * Fetch the object that implements the kernel's system calls.
     *
     * @return KernelApiInterface The kernel's API implementation.
     */
    public function api()
    {
        return $this->api;
    }

    /**
     * Fetch the object used to adapt values into co-routines.
     *
     * @return CoroutineAdaptorInterface The kernel's co-routine adaptor.
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
     * Fetch the ReactPHP event-loop.
     *
     * @return LoopInterface The ReactPHP event-loop.
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

    private $api;
    private $coroutineAdaptor;
    private $strandFactory;
    private $eventLoop;
    private $strands;
}

<?php
namespace Recoil\Kernel;

use Recoil\Coroutine\CoroutineAdaptor;
use Recoil\Coroutine\CoroutineAdaptorInterface;
use Recoil\Kernel\Api\KernelApi;
use Recoil\Kernel\Api\KernelApiInterface;
use Recoil\Kernel\Strand\StrandFactory;
use Recoil\Kernel\Strand\StrandFactoryInterface;
use Recoil\Kernel\Strand\StrandInterface;
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
        $this->terminating = false;
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
            if ($this->terminateStrands) {
                $strand->terminate();
            }

            $strand->tick();
        }

        if (0 !== $this->strands->count()) {
            $this->registerTick();
        } elseif ($this->stopEventLoop) {
            $this->eventLoop()->stop();
        }

        $this->terminateStrands = false;
        $this->stopEventLoop = false;
    }

    /**
     * Register the tick handler on the next event-loop tick.
     */
    protected function registerTick()
    {
        $this->eventLoop()->futureTick(
            function () {
                $this->tick();
            }
        );
    }

    /**
     * Terminate all strands and stop execution.
     *
     * The React event-loop can optionally be stopped when all strands have been
     * terminated.
     *
     * @param boolean $stopEventLoop Indicates whether or not the React event-loop should also be stopped.
     */
    public function stop($stopEventLoop = true)
    {
        $this->terminateStrands = true;
        $this->stopEventLoop = $stopEventLoop;
    }

    private $eventLoop;
    private $api;
    private $coroutineAdaptor;
    private $strandFactory;
    private $strands;
    private $terminateStrands;
    private $stopEventLoop;
}

<?php

namespace Recoil\Kernel;

use React\EventLoop\Factory as EventLoopFactory;
use React\EventLoop\LoopInterface;
use Recoil\Coroutine\CoroutineAdaptor;
use Recoil\Coroutine\StandardCoroutineAdaptor;
use Recoil\Kernel\Api\KernelApi;
use Recoil\Kernel\Api\StandardKernelApi;
use Recoil\Kernel\Strand\StandardStrandFactory;
use Recoil\Kernel\Strand\Strand;
use Recoil\Kernel\Strand\StrandFactory;

/**
 * The default kernel implementation.
 */
class StandardKernel implements Kernel
{
    /**
     * @param LoopInterface|null    $eventLoop        The React event-loop.
     * @param KernelApi|null        $api              The kernel's API implementation.
     * @param CoroutineAdaptor|null $coroutineAdaptor The kernel's coroutine adaptor.
     * @param StrandFactory|null    $strandFactory    The kernel's strand factory.
     */
    public function __construct(
        LoopInterface $eventLoop = null,
        KernelApi $api = null,
        CoroutineAdaptor $coroutineAdaptor = null,
        StrandFactory $strandFactory = null
    ) {
        if (null === $eventLoop) {
            $eventLoop = EventLoopFactory::create();
        }

        if (null === $api) {
            $api = new StandardKernelApi();
        }

        if (null === $coroutineAdaptor) {
            $coroutineAdaptor = new StandardCoroutineAdaptor();
        }

        if (null === $strandFactory) {
            $strandFactory = new StandardStrandFactory();
        }

        $this->eventLoop        = $eventLoop;
        $this->api              = $api;
        $this->coroutineAdaptor = $coroutineAdaptor;
        $this->strandFactory    = $strandFactory;
        $this->strands          = [];
        $this->terminateStrands = false;
    }

    /**
     * Execute a coroutine in a new strand of execution.
     *
     * The parameter may be any value that can be adapted into a coroutine by
     * the kernel's coroutine adaptor.
     *
     * @param mixed $coroutine The coroutine to execute.
     *
     * @return Strand The strand on which the coroutine will execute.
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
     * @param Strand The strand to attach.
     */
    public function attachStrand(Strand $strand)
    {
        if (!$this->strands) {
            $this->eventLoop->futureTick([$this, 'onTick']);
        }

        $this->strands[] = $strand;
    }

    /**
     * Detach an existing strand from this kernel.
     *
     * @param Strand The strand to detach.
     */
    public function detachStrand(Strand $strand)
    {
        $index = array_search($strand, $this->strands, true);

        if (false !== $index) {
            unset($this->strands[$index]);
        }
    }

    /**
     * Fetch the object that implements the kernel API.
     *
     * @return KernelApi The kernel's API implementation.
     */
    public function api()
    {
        return $this->api;
    }

    /**
     * Fetch the object used to adapt values into coroutines.
     *
     * @return CoroutineAdaptor The kernel's coroutine adaptor.
     */
    public function coroutineAdaptor()
    {
        return $this->coroutineAdaptor;
    }

    /**
     * Fetch the factory used to create new strands.
     *
     * @return StrandFactory The kernel's strand factory.
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
        $this->stopEventLoop    = $stopEventLoop;
    }

    /**
     * Step each of the strands attached to this kernel.
     *
     * @access private
     */
    public function onTick()
    {
        $strands = $this->strands;

        foreach ($strands as $strand) {
            if ($this->terminateStrands) {
                $strand->terminate();
            }

            $strand->tick();
        }

        if ($this->strands) {
            $this->eventLoop->futureTick([$this, 'onTick']);
        } elseif ($this->stopEventLoop) {
            $this->eventLoop->stop();
        }

        $this->terminateStrands = false;
        $this->stopEventLoop    = false;
    }

    private $eventLoop;
    private $api;
    private $coroutineAdaptor;
    private $strandFactory;
    private $strands;
    private $terminateStrands;
    private $stopEventLoop;
}

<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

use Recoil\Kernel\Api;
use Recoil\Kernel\KernelState;
use Recoil\Kernel\KernelTrait;
use Recoil\Kernel\SystemKernel;
use Recoil\Strand;

/**
 * The reference kernel implementation.
 */
final class ReferenceKernel implements SystemKernel
{
    /**
     * Create a new kernel.
     */
    public static function create(): self
    {
        $events = new EventQueue();
        $io = new IO();
        $api = new ReferenceApi($events, $io);

        return new self($events, $io, $api);
    }

    /**
     * Schedule a coroutine for execution on a new strand.
     *
     * Execution begins when the kernel is run; or, if called from within a
     * strand, when that strand cooperates.
     *
     * @param mixed $coroutine The coroutine to execute.
     */
    public function execute($coroutine): Strand
    {
        $strand = new ReferenceStrand(
            $this,
            $this->api,
            $this->nextId++,
            $coroutine
        );

        $strand->setTerminator(
            $this->events->schedule(
                0,
                function () use ($strand) {
                    $strand->start();
                }
            )
        );

        return $strand;
    }

    /**
     * Please note that this code is not part of the public API. It may be
     * changed or removed at any time without notice.
     *
     * @access private
     *
     * This constructor is public so that it may be used by auto-wiring
     * dependency injection containers. If you are explicitly constructing an
     * instance please use one of the static factory methods listed below.
     *
     * @see ReferenceKernel::create()
     *
     * @param EventQueue $events The queue used to schedule events.
     * @param IO         $io     The object used to perform IO.
     * @param Api        $api    The kernel API exposed to strands.
     */
    public function __construct(EventQueue $events, IO $io, Api $api)
    {
        $this->events = $events;
        $this->io = $io;
        $this->api = $api;
    }

    /**
     * The kernel's main event loop. Invoked inside the run() method.
     *
     * Loop must return when $this->state is KernelState::STOPPING.
     *
     * @return null
     */
    protected function loop()
    {
        do {
            $timeout = $this->events->tick();

            if ($this->state !== KernelState::RUNNING) {
                return;
            }

            $io = $this->io->tick($timeout);

            if ($this->state !== KernelState::RUNNING) {
                return;
            }
        } while ($timeout !== null || $io !== IO::INACTIVE);
    }

    use KernelTrait;

    /**
     * @var EventQueue The queue used to schedule events.
     */
    private $events;

    /**
     * @var IO The object used to perform IO.
     */
    private $io;

    /**
     * @var Api The kernel API exposed to strands.
     */
    private $api;

    /**
     * @var int The next strand ID.
     */
    private $nextId = 1;
}

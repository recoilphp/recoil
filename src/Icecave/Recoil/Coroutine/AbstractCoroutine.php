<?php
namespace Icecave\Recoil\Coroutine;

use Exception;
use Icecave\Recoil\Kernel\Strand\StrandInterface;

/**
 * A base class for co-routines that keeps track of next tick state behaviour.
 */
abstract class AbstractCoroutine implements CoroutineInterface
{
    public function __construct()
    {
        $this->tickLogic = function ($strand) {
            $this->call($strand);
        };
    }

    /**
     * Invoked when tick() is called for the first time.
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    abstract public function call(StrandInterface $strand);

    /**
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     * @param mixed           $value  The value passed to sendOnNextTick().
     */
    abstract public function resumeWithValue(StrandInterface $strand, $value);

    /**
     * Invoked when tick() is called after throwOnNextTick().
     *
     * @param StrandInterface $strand    The strand that is executing the co-routine.
     * @param Exception       $exception The exception passed to throwOnNextTick().
     */
    abstract public function resumeWithException(StrandInterface $strand, Exception $exception);

    /**
     * Invoked when tick() is called after terminateOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    abstract public function terminate(StrandInterface $strand);

    /**
     * Execute the next unit of work.
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function tick(StrandInterface $strand)
    {
        $tickLogic = $this->tickLogic;

        // Clear tickLogic so that an action must be explicitly enqueued before
        // tick() is called again.
        $this->tickLogic = null;

        $tickLogic($strand);
    }

    /**
     * Store a value to send to the co-routine on the next tick.
     *
     * @param mixed $value The value to send.
     */
    public function sendOnNextTick($value)
    {
        $this->tickLogic = function ($strand) use ($value) {
            $this->resumeWithValue($strand, $value);
        };
    }

    /**
     * Store an exception to send to the co-routine on the next tick.
     *
     * @param Exception $exception The exception to send.
     */
    public function throwOnNextTick(Exception $exception)
    {
        $this->tickLogic = function ($strand) use ($exception) {
            $this->resumeWithException($strand, $exception);
        };
    }

    /**
     * Instruct the co-routine to terminate execution on the next tick.
     */
    public function terminateOnNextTick()
    {
        $this->tickLogic = function ($strand) {
            $this->terminate($strand);
        };
    }

    private $tickLogic;
}

<?php
namespace Icecave\Recoil\Kernel;

use BadMethodCallException;
use Exception;
use Icecave\Recoil\Coroutine\CoroutineInterface;
use LogicException;

/**
 * Represents a system-call.
 *
 * The call is proxied on to the Kernel API implementation.
 */
class SystemCall implements CoroutineInterface
{
    /**
     * @param string $name      The name of the system-call to invoke.
     * @param array  $arguments The arguments to the system-call.
     */
    public function __construct($name, array $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    /**
     * Execute the next unit of work.
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function tick(StrandInterface $strand)
    {
        $method = [$strand->kernel()->api(), $this->name];

        if (is_callable($method)) {
            $strand->pop();
            $arguments = $this->arguments;
            array_unshift($arguments, $strand);
            call_user_func_array($method, $arguments);
        } else {
            $strand->throwException(
                new BadMethodCallException('Kernel API does not support the "' . $this->name . '" system-call.')
            );
        }
    }

    /**
     * Store a value to send to the co-routine on the next tick.
     *
     * @codeCoverageIgnore
     *
     * @param mixed $value The value to send.
     */
    public function sendOnNextTick($value)
    {
        throw new LogicException('Not supported.');
    }

    /**
     * Store an exception to send to the co-routine on the next tick.
     *
     * @codeCoverageIgnore
     *
     * @param Exception $exception The exception to send.
     */
    public function throwOnNextTick(Exception $exception)
    {
        throw new LogicException('Not supported.');
    }

    /**
     * Instruct the co-routine to terminate execution on the next tick.
     *
     * @codeCoverageIgnore
     */
    public function terminateOnNextTick()
    {
        throw new LogicException('Not supported.');
    }

    private $name;
    private $arguments;
}

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
     * Perform the next unit-of-work.
     *
     * @param StrandInterface $strand    The currently executing strand.
     * @param mixed           $value
     * @param Exception|null  $exception
     */
    public function tick(StrandInterface $strand, $value = null, Exception $exception = null)
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
     * Cancel execution of the co-routine.
     *
     * @codeCoverageIgnore
     */
    public function cancel()
    {
        throw new LogicException('Not supported.');
    }

    private $name;
    private $arguments;
}

<?php
namespace Icecave\Recoil\Kernel;

use BadMethodCallException;
use Exception;
use Icecave\Recoil\Coroutine\AbstractCoroutine;

/**
 * Represents a system-call.
 *
 * The call is proxied on to the Kernel API implementation.
 */
class SystemCall extends AbstractCoroutine
{
    /**
     * @param string $name      The name of the system-call to invoke.
     * @param array  $arguments The arguments to the system-call.
     */
    public function __construct($name, array $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;

        parent::__construct();
    }

    /**
     * Invoked when tick() is called for the first time.
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function call(StrandInterface $strand)
    {
        $method = [$strand->kernel()->api(), $this->name];

        if (is_callable($method)) {
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
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     * @param mixed $value The value passed to sendOnNextTick().
     */
    public function resume(StrandInterface $strand, $value)
    {
        $strand->returnValue($value);
    }

    /**
     * Invoked when tick() is called after throwOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     * @param Exception $exception The exception passed to throwOnNextTick().
     */
    public function error(StrandInterface $strand, Exception $exception)
    {
        $strand->throwException($exception);
    }

    /**
     * Invoked when tick() is called after terminateOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the co-routine.
     */
    public function terminate(StrandInterface $strand)
    {
        $strand->pop();
        $strand->terminate();
    }

    private $name;
    private $arguments;
}

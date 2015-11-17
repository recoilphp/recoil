<?php

namespace Recoil\Kernel\Api;

use BadMethodCallException;
use Recoil\Coroutine\Coroutine;
use Recoil\Coroutine\CoroutineTrait;
use Recoil\Kernel\Strand\Strand;

/**
 * Represents a call to a feature provided by the Kernel API.
 *
 * @see Recoil\Kernel\KernelApi
 * @see Recoil\Kernel\Kernel::api()
 */
class KernelApiCall implements Coroutine
{
    use CoroutineTrait;

    /**
     * @param string $name      The name of the kernel API function to invoke.
     * @param array  $arguments The arguments to the kernel API function.
     */
    public function __construct($name, array $arguments)
    {
        $this->name      = $name;
        $this->arguments = $arguments;
    }

    /**
     * Fetch the name of the kernel API function to invoke.
     *
     * @return string The name of the kernel API function to invoke.
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Fetch the arguments to the kernel API function.
     *
     * @return array The arguments to the kernel API function.
     */
    public function arguments()
    {
        return $this->arguments;
    }

    /**
     * Start the coroutine.
     *
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function call(Strand $strand)
    {
        $method = [$strand->kernel()->api(), $this->name];

        if (!is_callable($method)) {
            $strand->throwException(
                new BadMethodCallException(
                    'The kernel API does not have an operation named "' . $this->name . '".'
                )
            );

            return;
        }

        $strand->pop();

        $arguments = $this->arguments;

        array_unshift($arguments, $strand);

        // If the kernel API implementation returns a non-null value it is
        // treated as a coroutine to be executed. This allows implementation of
        // kernel API operations to be implemented as generators.
        $coroutine = call_user_func_array($method, $arguments);

        if (null !== $coroutine) {
            $strand->call($coroutine);
        }
    }

    private $name;
    private $arguments;
}

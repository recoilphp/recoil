<?php
namespace Recoil\Kernel\Api;

use BadMethodCallException;
use Recoil\Coroutine\AbstractCoroutine;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * Represents a call to a feature provided by the Kernel API.
 *
 * @see Recoil\Kernel\KernelApiInterface
 * @see Recoil\Kernel\KernelInterface::api()
 */
class KernelApiCall extends AbstractCoroutine
{
    /**
     * @param string $name      The name of the kernel API function to invoke.
     * @param array  $arguments The arguments to the kernel API function.
     */
    public function __construct($name, array $arguments)
    {
        $this->name = $name;
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
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function call(StrandInterface $strand)
    {
        $method = [$strand->kernel()->api(), $this->name()];

        if (is_callable($method)) {
            $strand->pop();
            $arguments = $this->arguments();
            array_unshift($arguments, $strand);
            call_user_func_array($method, $arguments);
        } else {
            $strand->throwException(
                new BadMethodCallException('The kernel API does not have an operation named "' . $this->name . '".')
            );
        }
    }

    private $name;
    private $arguments;
}

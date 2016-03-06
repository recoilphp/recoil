<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

/**
 * Represent a call to an API operation.
 */
final class ApiCall
{
    /**
     * @param string The operation name, corresponds to the methods in Api.
     */
    public $name;

    /**
     * @param array The operation arguments.
     */
    public $arguments;

    /**
     * @param string $name      The operation name, corresponds to the methods in Api.
     * @param array  $arguments The operation arguments.
     */
    public function __construct(string $name, array $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }
};

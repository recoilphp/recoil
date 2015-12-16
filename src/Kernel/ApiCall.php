<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

/**
 * An awaitable that invokes an API operation.
 */
final class ApiCall implements Awaitable
{
    /**
     * @param string $name      The operation name, corresponds to the methods in Api.
     * @param array  $arguments The operation arguments.
     */
    public function __construct(string $name, array $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    /**
     * Perform the work and resume the caller upon completion.
     *
     * @param Strand      $strand The executing strand.
     * @param Suspendable $caller The waiting object.
     * @param Api         $api    The kernel API.
     */
    public function await(Strand $strand, Suspendable $caller, Api $api)
    {
        $api->{$this->name}($strand, $caller, ...$this->arguments);
    }

    /**
     * @param string The operation name, corresponds to the methods in Api.
     */
    private $name;

    /**
     * @param array The operation arguments.
     */
    private $arguments;
};

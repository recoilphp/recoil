<?php

declare (strict_types = 1);

namespace Recoil\Exception;

use Exception;

/**
 * Holds exceptions produced by API operations that run multiple strands in
 * parallel.
 */
class CompositeException extends Exception
{
    /**
     * @param array<integer, Exception> The exceptions.
     */
    public function __construct(array $exceptions)
    {
        $this->exceptions = $exceptions;

        parent::__construct('Multiple exceptions occurred.');
    }

    /**
     * Get the exceptions.
     *
     * The array order matches the order of strand completion. The array keys
     * indicate the order in which the strands were started.
     *
     * @return array<integer, Exception> The exceptions.
     */
    public function exceptions() : array
    {
        return $this->exceptions;
    }

    /**
     * @var array<integer, Exception>
     */
    private $exceptions;
}

<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Exception;

use RuntimeException;

/**
 * An operation has timed out.
 */
class TimeoutException extends RuntimeException
{
    public function __construct(float $seconds)
    {
        parent::__construct(
            'The operation timed out after ' . $seconds . ' second(s).'
        );
    }
}

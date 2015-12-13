<?php

declare (strict_types = 1);

namespace Recoil\Exception;

use RuntimeException;

/**
 * A task has timed out.
 */
class TimeoutException extends RuntimeException
{
    public function __construct(float $seconds)
    {
        parent::__construct(
            \sprintf(
                'The operation timed out after %.2f second(s).',
                $seconds
            )
        );
    }
}

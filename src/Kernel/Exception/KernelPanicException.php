<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel\Exception;

use RuntimeException;
use Throwable;

/**
 * A kernel panic has occurred.
 */
class KernelPanicException extends RuntimeException
{
    public function __construct(
        string $message = 'Kernel panic.',
        Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}

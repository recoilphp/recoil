<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 * @final
 */
interface StrandState
{
    const READY = 0;
    const TICKING = 1;
    const SUSPENDED = 2;
    const SUCCESS = 3;
    const FAILED = 4;
    const TERMINATED = 5;
}

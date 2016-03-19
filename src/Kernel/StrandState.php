<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 */
interface StrandState
{
    const READY = 0;
    const RUNNING = 1;
    const SUSPENDED = 2;
    const EXIT_SUCCESS = 3;
    const EXIT_FAIL = 4;
    const EXIT_TERMINATED = 5;
}

<?php

namespace Recoil\Channel\Exception;

use Exception;

/**
 * A read or write operation was attempted on a channel that is locked.
 */
class ChannelLockedException extends Exception
{
    /**
     * @param Exception|null $previous The previous exception, if any.
     */
    public function __construct(Exception $previous = null)
    {
        parent::__construct('Channel is already in use by another strand.', 0, $previous);
    }
}

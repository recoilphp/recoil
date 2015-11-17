<?php

namespace Recoil\Coroutine\Exception;

use Exception;
use Icecave\Repr\Repr;

/**
 * Indicates that a promise was rejected.
 *
 * This exception is used to wrap rejection reasons that are not already
 * exception objects.
 */
class PromiseRejectedException extends Exception
{
    /**
     * @param mixed $reason The reason that the promise was rejected.
     */
    public function __construct($reason)
    {
        $this->reason = $reason;

        parent::__construct('Promise was rejected: ' . Repr::repr($reason) . '.');
    }

    /**
     * Fetch the rejection reason.
     *
     * @return mixed The reason that the promise was rejected.
     */
    public function reason()
    {
        return $this->reason;
    }

    private $reason;
}

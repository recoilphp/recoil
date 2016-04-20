<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use React\Promise\Deferred;
use Recoil\Kernel\Listener;
use Recoil\Kernel\Strand;
use Throwable;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 *
 * Adapts a React deferred object into a Recoil listener.
 */
final class DeferredAdaptor implements Listener
{
    /**
     * @param Deferred $deferred The deferred to settle when the strand exits.
     */
    public function __construct(Deferred $deferred)
    {
        $this->deferred = $deferred;
    }

    /**
     * Resume execution.
     *
     * @param mixed       $value  The value to send.
     * @param Strand|null $strand The strand that resumed this object, if any.
     *
     * @return null
     */
    public function send($value = null, Strand $strand = null)
    {
        $this->deferred->resolve($value);
    }

    /**
     * Resume execution, indicating an error state.
     *
     * @param Throwable   $exception The exception describing the error.
     * @param Strand|null $strand    The strand that resumed this object, if any.
     *
     * @return null
     */
    public function throw(Throwable $exception, Strand $strand = null)
    {
        $this->deferred->reject($exception);
    }

    /**
     * @var Deferred The deferred to settle when the strand exits.
     */
    private $deferred;
}

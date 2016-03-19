<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use React\Promise\Deferred;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandObserver;
use Throwable;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 *
 * A strand observer that forwards events on to a React deferred object.
 */
final class DeferredResolver implements StrandObserver
{
    /**
     * @param Deferred $deferred The deferred to settle when the strand completes.
     */
    public function __construct(Deferred $deferred)
    {
        $this->deferred = $deferred;
    }

    /**
     * A strand completed successfully.
     *
     * @param Strand $strand The strand.
     * @param mixed  $value  The result of the strand's entry point coroutine.
     */
    public function success(Strand $strand, $value)
    {
        $this->deferred->resolve($value);
    }

    /**
     * A strand failed due to an uncaught exception.
     *
     * @param Strand    $strand    The strand.
     * @param Throwable $exception The exception.
     */
    public function failure(Strand $strand, Throwable $exception)
    {
        $this->deferred->reject($exception);
    }

    /**
     * A strand was terminated.
     *
     * @param Strand $strand The strand.
     */
    public function terminated(Strand $strand)
    {
        $this->deferred->reject(new TerminatedException($strand));
    }

    /**
     * @var Deferred The deferred to settle when the strand completes.
     */
    private $deferred;
}

<?php

declare (strict_types = 1);

namespace Recoil;

use Exception;
use React\Promise\PromiseInterface;

trait PromiseTestTrait
{
    /**
     * Assert that a promise has been resolved.
     *
     * @param mixed $value The expected value.
     */
    public function assertResolved($promise)
    {
        list($resolved, $rejected, $value, $reason) = $this->check($promise);

        if ($resolved) {
            return $value;
        } elseif (!$rejected) {
            $this->fail('Promise was not settled.');
        } elseif ($reason instanceof Exception) {
            throw $reason;
        } elseif (is_string($reason)) {
            $this->fail($reason);
        } else {
            $this->fail('Promise was rejected.');
        }
    }

    /**
     * Assert that a promise has been resolved with a specific value.
     *
     * @param mixed            $value   The expected value.
     * @param PromiseInterface $promise The promise.
     */
    public function assertResolvedWith($value, $promise)
    {
        $this->assertEquals(
            $value,
            $this->assertResolved($promise)
        );
    }

    /**
     * Assert that a promise has been rejected.
     *
     * @param PromiseInterface $promise The promise.
     *
     * @return mixed The rejection reason.
     */
    public function assertRejected($promise)
    {
        list($resolved, $rejected, $value, $reason) = $this->check($promise);

        if ($rejected) {
            return $reason;
        }

        $this->fail('Promise was not rejected.');
    }

    /**
     * Assert that a promise has been rejected with a specific reason.
     *
     * @param mixed            $reason  The expected rejection reason.
     * @param PromiseInterface $promise The promise.
     */
    public function assertRejectedWith(Exception $reason, $promise)
    {
        $this->assertEquals(
            $reason,
            $this->assertRejected($promise)
        );
    }

    /**
     * Assert that a promise has not been settled.
     *
     * @param PromiseInterface $promise
     */
    public function assertNotSettled($promise)
    {
        list($resolved, $rejected, $value, $reason) = $this->check($promise);

        if ($reason instanceof Exception) {
            throw $reason;
        }

        if ($resolved) {
            $this->fail(
                'Promise was resolved.'
            );
        }

        if ($rejected) {
            $this->fail(
                'Promise was rejected with non-exception value.'
            );
        }
    }

    /**
     * @return tuple<bool, bool, mixed, mixed>
     */
    private function check($promise)
    {
        $this->assertInstanceOf(
            PromiseInterface::class,
            $promise
        );

        $resolved = false;
        $rejected = false;
        $value = null;
        $reason = null;

        $promise->then(
            function ($v) use (&$resolved, &$value) {
                $resolved = true;
                $value = $v;
            },
            function ($r) use (&$rejected, &$reason) {
                $rejected = true;
                $reason = $r;
            }
        );

        return [$resolved, $rejected, $value, $reason];
    }
}

<?php

namespace Recoil\Coroutine;

use Exception;
use PHPUnit_Framework_TestCase;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Promise\RejectedPromise;
use Recoil\Coroutine\Exception\PromiseRejectedException;
use Recoil\Kernel\StandardKernel;
use Recoil\Recoil;

/**
 * @covers Recoil\Coroutine\PromiseCoroutine
 * @covers Recoil\Coroutine\CoroutineTrait
 */
class PromiseCoroutineTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new StandardKernel();
    }

    public function testFulfilledPromise()
    {
        $value     = null;
        $coroutine = function () use (&$value) {
            $value = (yield new PromiseCoroutine(
                new FulfilledPromise(123),
                false
            ));
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertSame(123, $value);
    }

    public function testRejectedPromise()
    {
        $exception = null;
        $coroutine = function () use (&$exception) {
            try {
                yield new PromiseCoroutine(
                    new RejectedPromise(new Exception('This is the exception.')),
                    false
                );
            } catch (Exception $e) {
                $exception = $e;
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertInstanceOf('Exception', $exception);
        $this->assertSame('This is the exception.', $exception->getMessage());
    }

    public function testRejectedPromiseWithNonExceptionReason()
    {
        $exception = null;
        $coroutine = function () use (&$exception) {
            try {
                yield new PromiseCoroutine(
                    new RejectedPromise('This is the exception.'),
                    false
                );
            } catch (PromiseRejectedException $e) {
                $exception = $e;
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertInstanceOf(PromiseRejectedException::class, $exception);
        $this->assertSame('This is the exception.', $exception->reason());
    }

    public function testTerminateThenFulfill()
    {
        $deferred         = new Deferred();
        $promise          = $deferred->promise();
        $promiseCoroutine = new PromiseCoroutine($promise, false);

        $resumed   = null;
        $coroutine = function () use (&$resumed, $promiseCoroutine) {
            $resumed = false;
            yield $promiseCoroutine;
            $resumed = true;
        };

        $strand = $this->kernel->execute($coroutine());

        $canceller = function () use ($deferred, $strand) {
            $strand->terminate();
            yield;
            $deferred->resolve();
        };

        $this->kernel->execute($canceller());

        $this->kernel->eventLoop()->run();

        $this->assertFalse($resumed);
    }

    public function testTerminateThenReject()
    {
        $cancelled        = false;
        $promiseCanceller = function () use (&$cancelled) {
            $cancelled = true;
        };

        $promise = new Promise(function () {}, $promiseCanceller);
        $promiseCoroutine = new PromiseCoroutine($promise, true);

        $coroutine = function () use ($promiseCoroutine) {
            yield $promiseCoroutine;
        };

        $strand = $this->kernel->execute($coroutine());

        $canceller = function () use ($strand) {
            $strand->terminate();
            yield;
        };

        $this->kernel->execute($canceller());

        $this->kernel->eventLoop()->run();

        $this->assertTrue($cancelled);
    }
}

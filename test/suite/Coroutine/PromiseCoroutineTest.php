<?php
namespace Recoil\Coroutine;

use Exception;
use Recoil\Coroutine\Exception\PromiseRejectedException;
use Recoil\Kernel\Kernel;
use Recoil\Recoil;
use PHPUnit_Framework_TestCase;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\RejectedPromise;

/**
 * @covers Recoil\Coroutine\PromiseCoroutine
 * @covers Recoil\Coroutine\AbstractCoroutine
 */
class PromiseCoroutineTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = new Kernel;
    }

    public function testPromise()
    {
        $promise = new FulfilledPromise(123);
        $coroutine = new PromiseCoroutine($promise);

        $this->assertSame($promise, $coroutine->promise());
    }

    public function testFulfilledPromise()
    {
        $value = null;
        $coroutine = function () use (&$value) {
            $value = (yield new PromiseCoroutine(
                new FulfilledPromise(123)
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
                    new RejectedPromise(new Exception('This is the exception.'))
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
                    new RejectedPromise('This is the exception.')
                );
            } catch (PromiseRejectedException $e) {
                $exception = $e;
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        $this->assertInstanceOf(PromiseRejectedException::CLASS, $exception);
        $this->assertSame('This is the exception.', $exception->reason());
    }

    public function testTerminateThenFulfill()
    {
        $deferred = new Deferred;
        $promise = $deferred->promise();
        $promiseCoroutine = new PromiseCoroutine($promise);

        $resumed = null;
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

    public function testCancelThenReject()
    {
        $deferred = new Deferred;
        $promise = $deferred->promise();
        $promiseCoroutine = new PromiseCoroutine($promise);

        $resumed = null;
        $coroutine = function () use (&$resumed, $promiseCoroutine) {
            $resumed = false;
            yield $promiseCoroutine;
            $resumed = true;
        };

        $strand = $this->kernel->execute($coroutine());

        $canceller = function () use ($deferred, $strand) {
            $strand->terminate();
            yield;
            $deferred->reject();
        };

        $this->kernel->execute($canceller());

        $this->kernel->eventLoop()->run();

        $this->assertFalse($resumed);
    }
}

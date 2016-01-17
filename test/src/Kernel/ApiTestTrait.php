<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Eloquent\Phony\Phpunit\Phony;
use React\Promise\PromiseInterface;
use Recoil\CoroutineTestTrait;
use Recoil\Recoil;

/**
 * A suite of functional tests for the standard kernel API methods.
 */
trait ApiTestTrait
{
    public function testExecuteWithGenerator()
    {
        $spy = function () {
            return '<ok>';
            yield;
        };
        // $spy = Phony::spy(
        // );

        $promise = $this->api->execute($spy());
        $guard = $this->assertResolved($promise);

        $this->assertInstanceOf(
            PromiseGuard::class,
            $guard
        );

        $promise = $guard->promise();

        $this->assertNotSettled($promise);

        $this->eventLoop->run();

        $this->assertResolvedWith(
            '<ok>',
            $promise
        );

        // $this->assertInstanceOf(
        //     PromiseInterface::class,
        //     $promise
        // );

        // $this->assertResolvedWith(
        //     '<ok>',
        //     $promise
        // );

        // guarantee next-tick for generators
        // @todo replace with more specific feature
        // @see https://github.com/eloquent/phony/issues/102
        // $this->assertFalse(
            // $spy->firstCall()->hasCompleted()
        // );

        // $this->assertSame(
            // '<ok>',
            // yield $promise
        // );
    }

    public function recoilTestExecuteCallable()
    {
        $stub = Phony::stub()->returns('<ok>');

        $promise = yield Recoil::execute($stub);

        // guarantee next-tick for callables
        $stub->never()->called();

        $this->assertSame(
            '<ok>',
            yield $promise
        );
    }

    // public function execute($task) : PromiseInterface;
    // public function callback($task) : PromiseInterface;
    // public function cooperate() : PromiseInterface;
    // public function sleep(float $seconds) : PromiseInterface;
    // public function timeout(float $seconds, $task) : PromiseInterface;
    // public function terminate() : PromiseInterface;
    // public function all(...$tasks) : PromiseInterface;
    // public function any(...$task) : PromiseInterface;
    // public function some(int $count, ...$tasks) : PromiseInterface;
    // public function first(int $count, ...$tasks) : PromiseInterface;

    use CoroutineTestTrait;
}

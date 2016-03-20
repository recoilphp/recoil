<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;
use Exception;
use Generator;
use Recoil\Kernel\Api;
use Recoil\Kernel\Awaitable;
use Recoil\Kernel\AwaitableProvider;
use Recoil\Kernel\CoroutineProvider;
use Recoil\Kernel\Strand;

rit('can invoke generator as coroutine', function () {
    $spy = Phony::spy();
    $fn = function () use ($spy) {
        $spy(2);

        return 3;
        yield;
    };

    $spy(1);
    $spy(yield $fn());

    Phony::inOrder(
        $spy->calledWith(1),
        $spy->calledWith(2),
        $spy->calledWith(3)
    );
});

rit('can invoke generator as coroutine with yield from', function () {
    $spy = Phony::spy();
    $fn = function () use ($spy) {
        $spy(2);

        return 3;
        yield;
    };

    $spy(1);
    $spy(yield from $fn());

    Phony::inOrder(
        $spy->calledWith(1),
        $spy->calledWith(2),
        $spy->calledWith(3)
    );
});

rit('can invoke coroutine provider', function () {
    $spy = Phony::spy();
    $spy(1);
    $result = yield new class ($spy) implements CoroutineProvider
 {
     public function __construct($spy)
     {
         $this->spy = $spy;
     }

     public function coroutine() : Generator
     {
         $spy = $this->spy;
         $spy(2);

         return 3;
         yield;
     }
 };

    $spy($result);

    Phony::inOrder(
        $spy->calledWith(1),
        $spy->calledWith(2),
        $spy->calledWith(3)
    );
});

rit('can invoke awaitable provider', function () {
    $spy = Phony::spy();
    $spy(1);
    $result = yield new class implements AwaitableProvider
 {
     public function awaitable() : Awaitable
     {
         return new class implements Awaitable
 {
     public function await(Strand $strand, Api $api)
     {
         $strand->resume(2);
     }
 };
     }
 };

    $spy($result);

    Phony::inOrder(
        $spy->calledWith(1),
        $spy->calledWith(2)
    );
});

rit('can invoke awaitable', function () {
    $spy = Phony::spy();
    $spy(1);
    $result = yield new class implements Awaitable
 {
     public function await(Strand $strand, Api $api)
     {
         $strand->resume(2);
     }
 };

    $spy($result);

    Phony::inOrder(
        $spy->calledWith(1),
        $spy->calledWith(2)
    );
});

rit('exception propagates up the call-stack', function () {
    try {
        $fn = function () {
            throw new Exception('<exception>');
            yield;
        };

        yield $fn();
        assert(false, 'expected exception was not thrown');
    } catch (Exception $e) {
        expect($e->getMessage())->to->equal('<exception>');
    }
});

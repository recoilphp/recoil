<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;

context('can be invoked by yielding a thenable', function () {
    rit('that does fulfill', function () {
        $promise = Phony::partialMock(
            [
                'then' => function (callable $fulfill, callable $reject) {
                    $fulfill('<value>');
                },
            ]
        );

        expect(yield $promise->get())->to->equal('<value>');
    });

    rit('that does reject with throwable', function () {
        $promise = Phony::partialMock(
            [
                'then' => function (callable $fulfill, callable $reject) {
                    $reject(new \Exception('<rejected>'));
                },
            ]
        );

        try {
            yield $promise->get();
            expect(false)->to->equal('Expected exception was not thrown.');
        } catch (\Exception $e) {
            expect($e->getMessage())->to->equal('<rejected>');
        }
    });

    rit('that does reject with non throwable', function () {
        $promise = Phony::partialMock(
            [
                'then' => function (callable $fulfill, callable $reject) {
                    $reject('<rejected>');
                },
            ]
        );

        try {
            yield $promise->get();
            expect(false)->to->equal('Expected exception was not thrown.');
        } catch (\Exception $e) {
            expect($e->getMessage())->to->equal('<rejected>');
        }
    });

    rit('that does cancel', function () {
        $promise = Phony::partialMock(
            [
                'then' => function (callable $fulfill, callable $reject) {},
                'cancel' => function () {},
            ]
        );

        $strand = yield Recoil::execute(function () use ($promise) {
            yield $promise->get();
        });

        yield;

        $strand->terminate();

        $promise->cancel->called();
    });
});

context('can be invoked by yielding a thenable that is also a doneable', function () {
    rit('that does fulfill', function () {
        $promise = Phony::partialMock(
            [
                'then' => function (callable $fulfill, callable $reject) {},
                'done' => function (callable $fulfill, callable $reject) {
                    $fulfill('<value>');
                },
            ]
        );

        expect(yield $promise->get())->to->equal('<value>');
    });

    rit('that does reject with throwable', function () {
        $promise = Phony::partialMock(
            [
                'then' => function (callable $fulfill, callable $reject) {},
                'done' => function (callable $fulfill, callable $reject) {
                    $reject(new \Exception('<rejected>'));
                },
            ]
        );

        try {
            yield $promise->get();
            expect(false)->to->equal('Expected exception was not thrown.');
        } catch (\Exception $e) {
            expect($e->getMessage())->to->equal('<rejected>');
        }
    });

    rit('that does reject with non throwable', function () {
        $promise = Phony::partialMock(
            [
                'then' => function (callable $fulfill, callable $reject) {},
                'done' => function (callable $fulfill, callable $reject) {
                    $reject('<rejected>');
                },
            ]
        );

        try {
            yield $promise->get();
            expect(false)->to->equal('Expected exception was not thrown.');
        } catch (\Exception $e) {
            expect($e->getMessage())->to->equal('<rejected>');
        }
    });

    rit('that does cancel', function () {
        $promise = Phony::partialMock(
            [
                'then' => function (callable $fulfill, callable $reject) {},
                'done' => function (callable $fulfill, callable $reject) {},
                'cancel' => function () {},
            ]
        );

        $strand = yield Recoil::execute(function () use ($promise) {
            yield $promise->get();
        });

        yield;

        $strand->terminate();

        $promise->cancel->called();
    });
});

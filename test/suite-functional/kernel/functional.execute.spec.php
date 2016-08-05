<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Generator;
use InvalidArgumentException;
use Recoil\Kernel\Api;

it('accepts a generator object', function () {
    $this->kernel->execute((function () {
        echo '<ok>';

        return;
        yield;
    })());

    ob_start();
    $this->kernel->run();
    expect(ob_get_clean())->to->equal('<ok>');
});

it('accepts a generator function', function () {
    $this->kernel->execute(function () {
        echo '<ok>';

        return;
        yield;
    });

    ob_start();
    $this->kernel->run();
    expect(ob_get_clean())->to->equal('<ok>');
});

it('does not accept regular functions', function () {
    try {
        $this->kernel->execute(function () {
        });
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->to->equal('Callable must return a generator.');
    }
});

it('accepts a coroutine provider', function () {
    $this->kernel->execute(new class() implements CoroutineProvider {
        public function coroutine() : Generator
        {
            echo '<ok>';

            return;
            yield;
        }
    });

    ob_start();
    $this->kernel->run();
    expect(ob_get_clean())->to->equal('<ok>');
});

it('accepts an awaitable provider', function () {
    $this->kernel->execute(new class() implements AwaitableProvider {
        public function awaitable() : Awaitable
        {
            return new class() implements Awaitable {
                public function await(Listener $listener)
                {
                    echo '<ok>';
                }
            };
        }
    });

    ob_start();
    $this->kernel->run();
    expect(ob_get_clean())->to->equal('<ok>');
});

it('accepts an awaitable', function () {
    $this->kernel->execute(new class() implements Awaitable {
        public function await(Listener $listener)
        {
            echo '<ok>';
        }
    });

    ob_start();
    $this->kernel->run();
    expect(ob_get_clean())->to->equal('<ok>');
});

it('dispatches other types via the kernel api', function () {
    $this->kernel->execute([
        function () {
            echo '<ok>';

            return;
            yield;
        },
    ]);

    ob_start();
    $this->kernel->run();
    expect(ob_get_clean())->to->equal('<ok>');
});

it('returns the strand', function () {
    $strand = $this->kernel->execute('<coroutine>');
    expect($strand)->to->be->an->instanceof(Strand::class);
});

it('defers execution', function () {
    ob_start();
    $this->kernel->execute([
        function () {
            echo '<ok>';

            return;
            yield;
        },
    ]);
    expect(ob_get_clean())->to->equal('');

    ob_start();
    $this->kernel->run();
    expect(ob_get_clean())->to->equal('<ok>');
});

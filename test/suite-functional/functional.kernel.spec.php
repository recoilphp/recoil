<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Generator;
use InvalidArgumentException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Awaitable;
use Recoil\Kernel\AwaitableProvider;
use Recoil\Kernel\CoroutineProvider;
use Recoil\Kernel\Strand;

describe('->execute()', function () {
    it('accepts a generator object', function () {
        $this->kernel->execute((function () {
            echo '<ok>';

            return;
            yield;
        })());

        ob_start();
        $this->kernel->wait();
        expect(ob_get_clean())->to->equal('<ok>');
    });

    it('accepts a generator function', function () {
        $this->kernel->execute(function () {
            echo '<ok>';

            return;
            yield;
        });

        ob_start();
        $this->kernel->wait();
        expect(ob_get_clean())->to->equal('<ok>');
    });

    it('does not accept regular functions', function () {
        $this->kernel->execute(function () {});

        try {
            $this->kernel->wait();
        } catch (InvalidArgumentException $e) {
            expect($e->getMessage())->to->equal('Callable must return a generator.');
        }
    });

    it('accepts a coroutine provider', function () {
        $this->kernel->execute(new class implements CoroutineProvider
 {
     public function coroutine() : Generator
     {
         echo '<ok>';

         return;
         yield;
     }
 });

        ob_start();
        $this->kernel->wait();
        expect(ob_get_clean())->to->equal('<ok>');
    });

    it('accepts an awaitable provider', function () {
        $this->kernel->execute(new class implements AwaitableProvider
 {
     public function awaitable() : Awaitable
     {
         return new class implements Awaitable
 {
     public function await(Strand $strand, Api $api)
     {
         echo '<ok>';
     }
 };
     }
 });

        ob_start();
        $this->kernel->wait();
        expect(ob_get_clean())->to->equal('<ok>');
    });

    it('accepts an awaitable', function () {
        $this->kernel->execute(new class implements Awaitable
 {
     public function await(Strand $strand, Api $api)
     {
         echo '<ok>';
     }
 });

        ob_start();
        $this->kernel->wait();
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
        $this->kernel->wait();
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
        $this->kernel->wait();
        expect(ob_get_clean())->to->equal('<ok>');
    });
});

describe('->wait()', function () {
    it('waits for all strands to exit', function () {
        $this->kernel->execute(function () {
            echo 'a';

            return;
            yield;
        });
        $this->kernel->execute(function () {
            echo 'b';

            return;
            yield;
        });

        ob_start();
        $this->kernel->wait();
        expect(ob_get_clean())->to->equal('ab');
    });
});

describe('->interrupt()', function () {
    it('causes wait() to throw an exception', function () {
        $this->kernel->execute(function () {
            yield;
            assert(false, 'not interrupted');
        });
        $this->kernel->execute(function () {
            $this->kernel->interrupt(new Exception('<exception>'));

            return;
            yield;
        });

        try {
            $this->kernel->wait();
            assert(false, 'expected exception was not thrown');
        } catch (Exception $e) {
            expect($e->getMessage())->to->equal('<exception>');
        }
    });
});

describe('->stop()', function () {
    it('causes wait() to return', function () {
        $this->kernel->execute(function () {
            yield;
            assert(false, 'not stopped');
        });
        $this->kernel->execute(function () {
            $this->kernel->stop();

            return;
            yield;
        });

        $this->kernel->wait();
    });
});

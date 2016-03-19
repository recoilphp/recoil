<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;
use Exception;
use Recoil\Exception\TimeoutException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Strand;

context('kernel api', function () {

    describe('->execute()', function () {
        rit('runs a coroutine in a new strand', function () {
            $spy = Phony::spy(function () {
                return '<ok>';
                yield;
            });

            $strand = yield Recoil::execute($spy);
            expect($strand)->to->be->an->instanceof(Strand::class);

            $spy->never()->called();

            expect(yield $strand)->to->equal('<ok>');
        });
    });

    describe('->callback()', function () {
        rit('creates a callback that runs a coroutine in a new strand', function () {
            $spy = Phony::spy(function () {
                return;
                yield;
            });

            $fn = yield Recoil::callback($spy);
            expect($fn)->to->satisfy('is_callable');

            $spy->never()->called();

            $fn();

            $spy->never()->called();

            yield;

            $spy->called();
        });
    });

    describe('->cooperate()', function () {
        rit('yields control to another strand', function () {
            $spy = Phony::spy();

            yield Recoil::execute(function () use ($spy) {
                $spy(2);

                return;
                yield;
            });

            $spy(1);
            yield Recoil::cooperate();
            $spy(3);

            Phony::inOrder(
                $spy->calledWith(1),
                $spy->calledWith(2),
                $spy->calledWith(3)
            );
        });

        rit('can be invoked by yielding null', function () {
            $spy = Phony::spy();

            yield Recoil::execute(function () use ($spy) {
                $spy(2);

                return;
                yield;
            });

            $spy(1);
            yield;
            $spy(3);

            Phony::inOrder(
                $spy->calledWith(1),
                $spy->calledWith(2),
                $spy->calledWith(3)
            );
        });
    });

    describe('->sleep()', function () {
        rit('resumes execution after the specified number of seconds', function () {
            $time = microtime(true);
            yield Recoil::sleep(0.1);
            $diff = microtime(true) - $time;

            expect($diff)->to->be->within(0.075, 1.075);
        });

        rit('can be invoked by yielding a number', function () {
            $time = microtime(true);
            yield 0.05;
            $diff = microtime(true) - $time;

            expect($diff)->to->be->within(0.04, 0.06);
        });
    });

    describe('->timeout()', function () {
        rit('returns value if the coroutine returns before the timeout', function () {
            $result = yield Recoil::timeout(
                1,
                function () {
                    return '<ok>';
                    yield;
                }
            );

            expect($result)->to->equal('<ok>');
        });

        rit('propagates exception if the coroutine throws before the timeout', function () {
            try {
                yield Recoil::timeout(
                    1,
                    function () {
                        throw new Exception('<exception>');
                        yield;
                    }
                );
                assert(false, 'Expected exception was not thrown.');
            } catch (Exception $e) {
                expect($e->getMessage())->to->equal('<exception>');
            }
        });

        rit('throws a timeout exception if the coroutine takes too long', function () {
            try {
                yield Recoil::timeout(
                    0.05,
                    function () {
                        yield 0.1;
                    }
                );
            } catch (TimeoutException $e) {
                // ok ...
            }
        });
    });

    describe('->all()', function () {
        beforeEach(function () {
            $this->spy = Phony::spy();
            $this->fn1 = function () {
                ($this->spy)(1);
                yield;
                ($this->spy)(3);

                return 'a';
            };
            $this->fn2 = function () {
                ($this->spy)(2);
                yield;
                ($this->spy)(4);

                return 'b';
            };
        });

        rit('executes coroutines concurrently', function () {
            yield Recoil::all(
                ($this->fn1)(),
                ($this->fn2)()
            );

            Phony::inOrder(
                $this->spy->calledWith(1),
                $this->spy->calledWith(2),
                $this->spy->calledWith(3),
                $this->spy->calledWith(4)
            );

            yield;
        });

        rit('returns an array of return values', function () {
            expect(yield Recoil::all(
                ($this->fn1)(),
                ($this->fn2)()
            ))->to->equal(['a', 'b']);
        });

        context('when one of the coroutines throws an exception', function () {
            beforeEach(function () {
                $this->fn2 = function () {
                    throw new Exception('<exception>');
                    yield;
                };
            });

            rit('propagates the exception', function () {
                try {
                    yield Recoil::all(
                        ($this->fn1)(),
                        ($this->fn2)()
                    );
                    assert(false, 'Expected exception was not thrown.');
                } catch (Exception $e) {
                    expect($e->getMessage())->to->equal('<exception>');
                }
            });

            rit('terminates the other coroutine', function () {
                try {
                    yield Recoil::all(
                        ($this->fn1)(),
                        ($this->fn2)()
                    );
                } catch (Exception $e) {
                    // fall-through ...
                }

                $this->spy->never()->calledWith(3);
            });
        });
    });

});

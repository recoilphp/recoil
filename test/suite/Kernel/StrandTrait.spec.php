<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use AssertionError;
use Eloquent\Phony\Phony;
use Exception;
use Generator;
use InvalidArgumentException;
use Recoil\Kernel\Exception\StrandFailedException;
use Throwable;

describe(StrandTrait::class, function () {

    beforeEach(function () {
        $this->kernel = Phony::mock(Kernel::class);
        $this->api = Phony::mock(Api::class);

        $this->subject = Phony::partialMock(
            [Strand::class, StrandTrait::class],
            [
                123,
                $this->kernel->mock(),
                $this->api->mock(),
            ]
        );

        // Attach some observers to verify correct behaviour in other tests
        // observer #2 is detached and tearDown() verifies that it is never
        // used ...
        $this->observer1 = Phony::mock(StrandObserver::class);
        $this->observer2 = Phony::mock(StrandObserver::class);

        $this->subject->mock()->attachObserver($this->observer1->mock());
        $this->subject->mock()->attachObserver($this->observer2->mock());
        $this->subject->mock()->detachObserver($this->observer2->mock());
    });

    afterEach(function () {
        $this->observer2->noInteraction();
    });

    describe('->id()', function () {
        it('returns the ID that was passed to the constructor', function () {
            expect($this->subject->mock()->id())->to->equal(123);
        });
    });

    describe('->kernel()', function () {
        it('returns the kernel that was passed to the constructor', function () {
            expect($this->subject->mock()->kernel())->to->equal($this->kernel->mock());
        });
    });

    describe('->start()', function () {
        it('accepts a generator object', function () {
            $fn = Phony::spy(function () {
                yield '<key>' => '<value>';
            });

            $this->subject->mock()->start($fn());

            $this->api->dispatch->calledWith(
                $this->subject,
                '<key>',
                '<value>'
            );

            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('accepts a generator function', function () {
            $fn = Phony::spy(function () {
                yield '<key>' => '<value>';
            });

            $this->subject->mock()->start($fn);

            $this->api->dispatch->calledWith(
                $this->subject,
                '<key>',
                '<value>'
            );

            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('accepts a coroutine provider', function () {
            $provider = Phony::mock(CoroutineProvider::class);
            $provider->coroutine->does(
                function () { yield '<key>' => '<value>'; }
            );

            $this->subject->mock()->start($provider->mock());

            $this->api->dispatch->calledWith(
                $this->subject,
                '<key>',
                '<value>'
            );
        });

        it('throws when passed a regular function', function () {
            expect(function () {
                $this->subject->mock()->start(function () {});
            })->to->throw(
                InvalidArgumentException::class,
                'Callable must return a generator.'
            );
        });

        it('dispatches other types via the kernel api', function () {
            $this->subject->mock()->start('<value>');

            $this->api->dispatch->calledWith(
                $this->subject,
                0,
                '<value>'
            );
        });
    });

    describe('->resume()', function () {
        it('sends the value to the coroutine', function () {
            $fn = Phony::spy(function () {
                yield;
            });

            $this->subject->mock()->start($fn);

            $this->subject->mock()->resume('<result>');
            $fn->received('<result>');
        });

        it('can be invoked from inside ->tick()', function () {
            $fn = Phony::spy(function () {
                yield;
            });

            $this->api->dispatch->does(function () {
                $this->subject->mock()->resume('<result>');
            });

            $this->subject->mock()->start($fn);

            $fn->received('<result>');
        });
    });

    describe('->throw()', function () {
        it('throws the exception to the coroutine', function () {
            $fn = Phony::spy(function () {
                yield;
            });

            $this->subject->mock()->start($fn);

            $exception = Phony::mock(Throwable::class);
            $this->subject->mock()->throw($exception->mock());
            $fn->receivedException($exception);
        });

        it('can be invoked from inside ->tick()', function () {
            $fn = Phony::spy(function () {
                yield;
            });

            $exception = Phony::mock(Throwable::class);
            $this->api->dispatch->does(function () use ($exception) {
                $this->subject->mock()->throw($exception->mock());
            });

            $this->subject->mock()->start($fn);

            $fn->receivedException($exception);
        });
    });

    describe('->terminate()', function () {
        it('invokes the terminator function', function () {
            $fn = Phony::spy();
            $this->subject->mock()->setTerminator($fn);

            $this->subject->mock()->terminate();

            $fn->once()->calledWith($this->subject);
        });

        it('notifies observers', function () {
            $this->subject->mock()->terminate();
            $this->observer1->terminated->once()->calledWith($this->subject);
        });

        it('interrupts the kernel when an observer throws', function () {
            $exception = new Exception('<observer-exception>');
            $this->observer1->terminated->throws($exception);

            $this->subject->mock()->terminate();
            $this->kernel->interrupt->calledWith($exception);
        });
    });

    describe('->awaitable()', function () {
        it('attaches a StrandWaitOne instance to the strand', function () {
            $awaitable = $this->subject->mock()->awaitable();
            expect($awaitable)->to->loosely->equal(new StrandWaitOne($this->subject->mock()));
        });
    });

    describe('->tick()', function () {
        context('when a coroutine returns a value', function () {
            it('propagates the value up the call-stack', function () {
                $fn2 = function () {
                    return '<result>';
                    yield;
                };

                $fn1 = Phony::spy(function () use ($fn2) {
                    yield $fn2();

                    return '<ok>';
                });

                $this->subject->mock()->start($fn1);

                $fn1->received('<result>');

                $this->observer1->success->calledWith(
                    $this->subject,
                    '<ok>'
                );
            });

            it('notifies observers when the top of the stack is reached', function () {
                $fn = function () {
                    return '<result>';
                    yield;
                };

                $this->subject->mock()->start($fn);

                $this->observer1->success->calledWith(
                    $this->subject,
                    '<result>'
                );
            });

            it('discards the value when there are no observers', function () {
                $this->subject->mock()->detachObserver($this->observer1->mock());

                $fn = function () {
                    return;
                    yield;
                };

                $this->subject->mock()->start($fn);
            });

            it('interrupts the kernel when an observer throws', function () {
                $exception = new Exception('<exception>');
                $this->observer1->success->throws($exception);

                $fn = function () {
                    return;
                    yield;
                };

                $this->subject->mock()->start($fn);

                $this->kernel->interrupt->calledWith($exception);
            });
        });

        context('when a coroutine throws an exception', function () {
            it('propagates the exception up the call-stack', function () {
                $exception = Phony::mock(Throwable::class);

                $fn2 = function () use ($exception) {
                    throw $exception->mock();
                    yield;
                };

                $fn1 = Phony::spy(function () use ($fn2) {
                    try {
                        yield $fn2();
                    } catch (Throwable $e) {
                        //ignore ...
                    }

                    return '<ok>';
                });

                $this->subject->mock()->start($fn1);

                $fn1->receivedException($exception);

                $this->observer1->success->calledWith(
                    $this->subject,
                    '<ok>'
                );
            });

            it('notifies observers when the top of the stack is reached', function () {
                $exception = Phony::mock(Throwable::class);

                $fn = function () use ($exception) {
                    throw $exception->mock();
                    yield;
                };

                $this->subject->mock()->start($fn);

                $this->observer1->failure->calledWith(
                    $this->subject,
                    $exception
                );
            });

            it('interrupts the kernel when there are no observers', function () {
                $this->subject->mock()->detachObserver($this->observer1->mock());

                $exception = new Exception('<exception>');

                $fn = function () use ($exception) {
                    throw $exception;
                    yield;
                };

                $this->subject->mock()->start($fn);

                $this->kernel->interrupt->calledWith(
                    new StrandFailedException(
                        $this->subject->mock(),
                        $exception
                    )
                );
            });

            it('interrupts the kernel when an observer throws', function () {
                $exception = new Exception('<observer-exception>');
                $this->observer1->failure->throws($exception);

                $fn = function () {
                    throw new Exception('<coroutine-exception>');
                    yield;
                };

                $this->subject->mock()->start($fn);

                $this->kernel->interrupt->calledWith($exception);
            });
        });

        context('when a coroutine yields', function () {
            it('invokes coroutines from coroutine providers', function () {
                $fn = Phony::spy(function () {
                    return yield new class implements CoroutineProvider
 {
     public function coroutine() : Generator
     {
         return '<result>';
         yield;
     }
 };
                });

                $this->subject->mock()->start($fn);

                $fn->received('<result>');
            });

            it('dispatches kernel api calls', function () {
                $fn = Phony::spy(function () {
                    yield new ApiCall('<name>', [1, 2, 3]);
                });

                $this->subject->mock()->start($fn);

                $this->api->{'<name>'}->calledWith(
                    $this->subject,
                    1,
                    2,
                    3
                );

                $fn->never()->received();
                $fn->never()->receivedException();
            });

            it('attaches the strand to awaitables', function () {
                $awaitable = Phony::mock(Awaitable::class);

                $fn = Phony::spy(function () use ($awaitable) {
                    yield $awaitable->mock();
                });

                $this->subject->mock()->start($fn);

                $awaitable->await->calledWith(
                    $this->subject,
                    $this->api
                );

                $fn->never()->received();
                $fn->never()->receivedException();
            });

            it('attaches the strand to awaitables from awaitable providers', function () {
                $provider = Phony::mock(AwaitableProvider::class);
                $awaitable = Phony::mock(Awaitable::class);
                $provider->awaitable->returns($awaitable);

                $fn = Phony::spy(function () use ($provider) {
                    yield $provider->mock();
                });

                $this->subject->mock()->start($fn);

                $awaitable->await->calledWith(
                    $this->subject,
                    $this->api
                );

                $fn->never()->received();
                $fn->never()->receivedException();
            });

            it('forwards other values to the api for dispatch', function () {
                $this->subject->mock()->start(function () {
                    yield '<value>';
                });

                $this->api->dispatch->calledWith(
                    $this->subject,
                    0,
                    '<value>'
                );
            });

            it('propagates exceptions thrown during handling of the yielded value', function () {
                $exception = Phony::mock(Throwable::class);
                $this->api->dispatch->throws($exception);

                $fn = Phony::spy(function () {
                    yield;
                });

                $this->subject->mock()->start($fn);

                $fn->receivedException($exception);

                $this->observer1->failure->calledWith(
                    $this->subject,
                    $exception
                );
            });
        });
    });

    context('when the strand has completed', function () {
        beforeEach(function () {
            $this->subject->mock()->start(function () {
                return;
                yield;
            });
        });

        it('->start() fails', function () {
            expect(function () {
                $this->subject->mock()->start('<value>');
            })->to->throw(
                AssertionError::class,
                'strand can not be started multiple times'
            );
        });

        it('->resume() fails', function () {
            expect(function () {
                $this->subject->mock()->resume('<result>');
            })->to->throw(
                AssertionError::class,
                'strand must be suspended to resume'
            );
        });

        it('->throw() fails', function () {
            expect(function () {
                $exception = Phony::mock(Throwable::class);
                $this->subject->mock()->throw($exception->mock());
            })->to->throw(
                AssertionError::class,
                'strand must be suspended to resume'
            );
        });

        it('->terminate() fails', function () {
            expect(function () {
                $this->subject->mock()->terminate();
            })->to->throw(
                AssertionError::class,
                'strand can not be terminated after it has exited'
            );
        });
    });

    context('when the strand has failed', function () {
        beforeEach(function () {
            $this->subject->mock()->start(function () {
                throw new Exception('<exception>');
                yield;
            });
        });

        it('->start() fails', function () {
            expect(function () {
                $this->subject->mock()->start('<value>');
            })->to->throw(
                AssertionError::class,
                'strand can not be started multiple times'
            );
        });

        it('->resume() fails', function () {
            expect(function () {
                $this->subject->mock()->resume('<result>');
            })->to->throw(
                AssertionError::class,
                'strand must be suspended to resume'
            );
        });

        it('->throw() fails', function () {
            expect(function () {
                $exception = Phony::mock(Throwable::class);
                $this->subject->mock()->throw($exception->mock());
            })->to->throw(
                AssertionError::class,
                'strand must be suspended to resume'
            );
        });

        it('->terminate() fails', function () {
            expect(function () {
                $this->subject->mock()->terminate();
            })->to->throw(
                AssertionError::class,
                'strand can not be terminated after it has exited'
            );
        });
    });

    context('when the strand has been terminated', function () {
        beforeEach(function () {
            $this->subject->mock()->terminate();
        });

        it('->start() does nothing', function () {
            $this->subject->mock()->start('<value>');
            $this->api->noInteraction();
        });

        it('->resume() does nothing', function () {
            $fn = Phony::spy(function () {
                yield;
            });

            $this->subject->mock()->start($fn);
            $this->subject->mock()->terminate();
            $this->subject->mock()->resume('<result>');

            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('->throw() does nothing', function () {
            $fn = Phony::spy(function () {
                yield;
            });

            $this->subject->mock()->start($fn);
            $this->subject->mock()->terminate();

            $exception = Phony::mock(Throwable::class);
            $this->subject->mock()->throw($exception->mock());

            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('->terminate() does nothing', function () {
            $this->subject->mock()->terminate();
        });
    });

});

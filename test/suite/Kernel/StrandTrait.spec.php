<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use AssertionError;
use Eloquent\Phony\Phony;
use Exception;
use Generator;
use InvalidArgumentException;
use Recoil\Kernel\Exception\StrandFailedException;
use Recoil\Kernel\Exception\StrandObserverFailedException;
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

        $this->observer = Phony::mock(StrandObserver::class);
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
            $fn = Phony::stub()->generates(['<key>' => '<value>'])->returns();
            $this->subject->mock()->start($fn());

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('accepts a generator function', function () {
            $fn = Phony::stub();
            $fn->generates(['<key>' => '<value>']);
            $this->subject->mock()->start($fn);

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('accepts a coroutine provider', function () {
            $provider = Phony::mock(CoroutineProvider::class);
            $provider->coroutine->generates(['<key>' => '<value>']);
            $this->subject->mock()->start($provider->mock());

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
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
            $this->api->dispatch->calledWith($this->subject, 0, '<value>');
        });
    });

    describe('->resume()', function () {
        it('sends the value to the coroutine', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            $this->subject->mock()->start($fn);

            $this->subject->mock()->resume('<result>');
            $fn->received('<result>');
        });

        it('can be invoked from inside ->tick()', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            $this->api->dispatch->does(function () {
                $this->subject->mock()->resume('<result>');
            });
            $this->subject->mock()->start($fn);

            $fn->received('<result>');
        });
    });

    describe('->throw()', function () {
        it('throws the exception to the coroutine', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            $this->subject->mock()->start($fn);
            $exception = Phony::mock(Throwable::class);
            $this->subject->mock()->throw($exception->mock());

            $fn->receivedException($exception);
        });

        it('can be invoked from inside ->tick()', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
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

        it('notifies observer', function () {
            $this->subject->mock()->setObserver($this->observer->mock());
            $this->subject->mock()->terminate();

            $this->observer->terminated->once()->calledWith($this->subject);
        });

        it('interrupts the kernel when an observer throws', function () {
            $exception = new Exception('<observer-exception>');
            $this->observer->terminated->throws($exception);
            $this->subject->mock()->setObserver($this->observer->mock());
            $this->subject->mock()->terminate();

            $this->kernel->interrupt->calledWith(
                new StrandObserverFailedException(
                    $this->subject->mock(),
                    $this->observer->mock(),
                    $exception
                )
            );
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
                $fn = Phony::spy(function () {
                    yield (function () {
                        return '<result>';
                        yield;
                    })();

                    return '<ok>';
                });

                $this->subject->mock()->start($fn);

                $fn->received('<result>');
            });

            it('notifies observers when the top of the stack is reached', function () {
                $this->subject->mock()->setObserver($this->observer->mock());
                $this->subject->mock()->start(
                    Phony::stub()->generates()->returns('<result>')
                );

                $this->observer->success->calledWith($this->subject, '<result>');
            });

            it('interrupts the kernel when an observer throws', function () {
                $exception = new Exception('<exception>');
                $this->observer->success->throws($exception);
                $this->subject->mock()->setObserver($this->observer->mock());
                $this->subject->mock()->start(
                    Phony::stub()->generates()->returns()
                );

                $this->kernel->interrupt->calledWith(
                    new StrandObserverFailedException(
                        $this->subject->mock(),
                        $this->observer->mock(),
                        $exception
                    )
                );
            });
        });

        context('when a coroutine throws an exception', function () {
            it('propagates the exception up the call-stack', function () {
                $exception = Phony::mock(Throwable::class);
                $fn = Phony::spy(function () use ($exception) {
                    yield (function () use ($exception) {
                        throw $exception->mock();
                        yield;
                    })();
                });

                $this->subject->mock()->start($fn);
                $fn->receivedException($exception);
            });

            it('interrupts the kernel when there is no observer', function () {
                $exception = new Exception('<exception>');
                $this->subject->mock()->start(
                    Phony::stub()->generates()->throws($exception)
                );

                $this->kernel->interrupt->calledWith(
                    new StrandFailedException(
                        $this->subject->mock(),
                        $exception
                    )
                );
            });

            it('notifies observer when the top of the stack is reached', function () {
                $exception = new Exception('<exception>');
                $this->subject->mock()->setObserver($this->observer->mock());
                $this->subject->mock()->start(
                    Phony::stub()->generates()->throws($exception)
                );

                $this->observer->failure->calledWith(
                    $this->subject,
                    $exception
                );
            });

            it('interrupts the kernel when an observer throws', function () {
                $observerException = new Exception('<observer-exception>');
                $this->observer->failure->throws($observerException);
                $strandException = new Exception('<exception>');
                $this->subject->mock()->setObserver($this->observer->mock());
                $this->subject->mock()->start(
                    Phony::stub()->generates()->throws($strandException)
                );

                $this->kernel->interrupt->calledWith(
                    new StrandObserverFailedException(
                        $this->subject->mock(),
                        $this->observer->mock(),
                        $observerException
                    )
                );
            });
        });

        context('when a coroutine yields', function () {
            it('invokes coroutines from coroutine providers', function () {
                $provider = Phony::mock(CoroutineProvider::class);
                $provider->coroutine->generates()->returns('<result>');
                $fn = Phony::stub();
                $fn->generates([$provider->mock()]); // @todo https://github.com/eloquent/phony/issues/144
                $this->subject->mock()->start($fn);

                $fn->received('<result>');
            });

            it('dispatches kernel api calls', function () {
                $fn = Phony::stub();
                $fn->generates([new ApiCall('<name>', [1, 2, 3])]);
                $this->subject->mock()->start($fn);

                $this->api->{'<name>'}->calledWith($this->subject, 1, 2, 3);
                $fn->never()->received();
                $fn->never()->receivedException();
            });

            it('attaches the strand to awaitables', function () {
                $awaitable = Phony::mock(Awaitable::class);
                $fn = Phony::stub();
                $fn->generates([$awaitable->mock()]); // @todo https://github.com/eloquent/phony/issues/144
                $this->subject->mock()->start($fn);

                $awaitable->await->calledWith($this->subject, $this->api);
                $fn->never()->received();
                $fn->never()->receivedException();
            });

            it('attaches the strand to awaitables from awaitable providers', function () {
                $awaitable = Phony::mock(Awaitable::class);
                $provider = Phony::mock(AwaitableProvider::class);
                $provider->awaitable->returns($awaitable);
                $fn = Phony::stub();
                $fn->generates([$provider->mock()]); // @todo https://github.com/eloquent/phony/issues/144
                $this->subject->mock()->start($fn);

                $awaitable->await->calledWith($this->subject, $this->api);
                $fn->never()->received();
                $fn->never()->receivedException();
            });

            it('forwards other values to the api for dispatch', function () {
                $this->subject->mock()->start(
                    Phony::stub()->generates(['<value>'])->returns()
                );

                $this->api->dispatch->calledWith($this->subject, 0, '<value>');
            });

            it('propagates exceptions thrown during handling of the yielded value', function () {
                $exception = Phony::mock(Throwable::class);
                $this->api->dispatch->throws($exception);
                $fn = Phony::stub()->generates([null])->returns();
                $this->subject->mock()->start($fn);

                $fn->receivedException($exception);
            });
        });
    });

    context('when the strand has completed', function () {
        beforeEach(function () {
            $this->subject->mock()->start(
                Phony::stub()->generates()->returns()
            );
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
            $this->subject->mock()->start(
                Phony::stub()->generates()->throws(new Exception('<exception>'))
            );
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
            $fn = Phony::stub();
            $fn->generates([null]);
            $this->subject->mock()->start($fn);
            $this->subject->mock()->terminate();
            $this->subject->mock()->resume('<result>');

            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('->throw() does nothing', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
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

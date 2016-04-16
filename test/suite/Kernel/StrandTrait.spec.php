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
        $this->initializeSubject = function ($entryPoint = null) {
            $this->subject = Phony::partialMock(
                [Strand::class, Awaitable::class, StrandTrait::class],
                [
                    $this->kernel->mock(),
                    $this->api->mock(),
                    123,
                    $entryPoint,
                ]
            );
        };

        ($this->initializeSubject)();

        $this->observer = Phony::mock(StrandObserver::class);

        $this->waiter1 = Phony::mock(Strand::class);
        $this->waiter2 = Phony::mock(Strand::class);
    });

    describe('->__construct()', function () {
        it('accepts a generator object', function () {
            $fn = Phony::stub()->generates(['<key>' => '<value>'])->returns();
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('accepts a generator function', function () {
            $fn = Phony::stub();
            $fn->generates(['<key>' => '<value>']);
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('accepts a coroutine provider', function () {
            $provider = Phony::mock(CoroutineProvider::class);
            $provider->coroutine->generates(['<key>' => '<value>']);
            ($this->initializeSubject)($provider->mock());
            $this->subject->mock()->start();

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
        });

        it('throws when passed a regular function', function () {
            expect(function () {
                ($this->initializeSubject)(function () {});
            })->to->throw(
                InvalidArgumentException::class,
                'Callable must return a generator.'
            );
        });

        it('dispatches other types via the kernel api', function () {
            ($this->initializeSubject)('<value>');
            $this->subject->mock()->start();
            $this->api->dispatch->calledWith($this->subject, 0, '<value>');
        });
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
        context('when a coroutine returns a value', function () {
            it('propagates the value up the call-stack', function () {
                $fn = Phony::spy(function () {
                    yield (function () {
                        return '<result>';
                        yield;
                    })();

                    return '<ok>';
                });

                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();

                $fn->received('<result>');
            });

            context('when the top of the call-stack is reached', function () {
                it('notifies the observer', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );
                    $this->subject->mock()->setObserver($this->observer->mock());
                    $this->subject->mock()->start();

                    $this->observer->success->calledWith($this->subject, '<result>');
                });

                it('triggers an exception on the kernel when the observer throws', function () {
                    $exception = new Exception('<exception>');
                    $this->observer->success->throws($exception);

                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns()
                    );
                    $this->subject->mock()->setObserver($this->observer->mock());
                    $this->subject->mock()->start();

                    $this->kernel->triggerException->calledWith(
                        new StrandObserverFailedException(
                            $this->subject->mock(),
                            $this->observer->mock(),
                            $exception
                        )
                    );
                });

                it('resumes waiting strands', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );
                    $this->subject->mock()->await($this->waiter1->mock(), $this->api->mock());
                    $this->subject->mock()->await($this->waiter2->mock(), $this->api->mock());
                    $this->subject->mock()->start();

                    $this->waiter1->resume->calledWith();
                    $this->waiter2->resume->calledWith();
                });
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

                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();
                $fn->receivedException($exception);
            });

            context('when the top of the call-stack is reached', function () {
                it('triggers an exception on the kernel when there is no observer', function () {
                    $exception = new Exception('<exception>');
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($exception)
                    );
                    $this->subject->mock()->start();

                    $this->kernel->triggerException->calledWith(
                        new StrandFailedException(
                            $this->subject->mock(),
                            $exception
                        )
                    );
                });

                it('notifies the observer', function () {
                    $exception = new Exception('<exception>');
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($exception)
                    );
                    $this->subject->mock()->setObserver($this->observer->mock());
                    $this->subject->mock()->start();

                    $this->observer->failure->calledWith(
                        $this->subject,
                        $exception
                    );
                });

                it('triggers an exception on the kernel when an observer throws', function () {
                    $observerException = new Exception('<observer-exception>');
                    $this->observer->failure->throws($observerException);
                    $strandException = new Exception('<exception>');

                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($strandException)
                    );
                    $this->subject->mock()->setObserver($this->observer->mock());
                    $this->subject->mock()->start();

                    $this->kernel->triggerException->calledWith(
                        new StrandObserverFailedException(
                            $this->subject->mock(),
                            $this->observer->mock(),
                            $observerException
                        )
                    );
                });

                it('resumes waiting strands', function () {
                    $exception = new Exception('<exception>');

                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($exception)
                    );
                    $this->subject->mock()->await($this->waiter1->mock(), $this->api->mock());
                    $this->subject->mock()->await($this->waiter2->mock(), $this->api->mock());
                    $this->subject->mock()->start();

                    $this->waiter1->resume->calledWith();
                    $this->waiter2->resume->calledWith();
                });
            });
        });

        context('when a coroutine yields', function () {
            it('invokes coroutines from coroutine providers', function () {
                $provider = Phony::mock(CoroutineProvider::class);
                $provider->coroutine->generates()->returns('<result>');
                $fn = Phony::stub();
                $fn->generates([$provider->mock()]); // @todo https://github.com/eloquent/phony/issues/144
                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();

                $fn->received('<result>');
            });

            it('dispatches kernel api calls', function () {
                $fn = Phony::stub();
                $fn->generates([new ApiCall('<name>', [1, 2, 3])]);
                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();

                $this->api->{'<name>'}->calledWith($this->subject, 1, 2, 3);
                $fn->never()->received();
                $fn->never()->receivedException();
            });

            it('dispatches kernel api calls implemented as coroutines', function () {
                $this->api->{'<name>'}->generates()->returns('<result>');
                $fn = Phony::stub();
                $fn->generates([new ApiCall('<name>', [1, 2, 3])]);
                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();

                $fn->received('<result>');
            });

            it('attaches the strand to awaitables', function () {
                $awaitable = Phony::mock(Awaitable::class);
                $fn = Phony::stub();
                $fn->generates([$awaitable->mock()]); // @todo https://github.com/eloquent/phony/issues/144
                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();

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
                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();

                $awaitable->await->calledWith($this->subject, $this->api);
                $fn->never()->received();
                $fn->never()->receivedException();
            });

            it('forwards other values to the api for dispatch', function () {
                ($this->initializeSubject)(
                    Phony::stub()->generates(['<value>'])->returns()
                );
                $this->subject->mock()->start();

                $this->api->dispatch->calledWith($this->subject, 0, '<value>');
            });

            it('propagates exceptions thrown during handling of the yielded value', function () {
                $exception = Phony::mock(Throwable::class);
                $this->api->dispatch->throws($exception);
                $fn = Phony::stub()->generates([null])->returns();
                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();

                $fn->receivedException($exception);
            });
        });
    });

    describe('->resume()', function () {
        it('sends the value to the coroutine', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();

            $this->subject->mock()->resume('<result>');
            $fn->received('<result>');
        });

        it('can be invoked from inside ->start()', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            $this->api->dispatch->does(function () {
                $this->subject->mock()->resume('<result>');
            });
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();

            $fn->received('<result>');
        });
    });

    describe('->throw()', function () {
        it('throws the exception to the coroutine', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();
            $exception = Phony::mock(Throwable::class);
            $this->subject->mock()->throw($exception->mock());

            $fn->receivedException($exception);
        });

        it('can be invoked from inside ->start()', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            $exception = Phony::mock(Throwable::class);
            $this->api->dispatch->does(function () use ($exception) {
                $this->subject->mock()->throw($exception->mock());
            });
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();

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

        it('notifies the observer', function () {
            $this->subject->mock()->setObserver($this->observer->mock());
            $this->subject->mock()->terminate();

            $this->observer->terminated->once()->calledWith($this->subject);
        });

        it('resumes waiting strands', function () {
            $this->subject->mock()->await($this->waiter1->mock(), $this->api->mock());
            $this->subject->mock()->await($this->waiter2->mock(), $this->api->mock());
            $this->subject->mock()->terminate();

            $this->waiter1->resume->calledWith();
            $this->waiter2->resume->calledWith();
        });

        it('triggers an exception on the kernel when an observer throws', function () {
            $exception = new Exception('<observer-exception>');
            $this->observer->terminated->throws($exception);
            $this->subject->mock()->setObserver($this->observer->mock());
            $this->subject->mock()->terminate();

            $this->kernel->triggerException->calledWith(
                new StrandObserverFailedException(
                    $this->subject->mock(),
                    $this->observer->mock(),
                    $exception
                )
            );
        });
    });

    describe('->hasExited()', function () {
        it('returns false', function () {
            expect($this->subject->mock()->hasExited())->to->be->false;
        });
    });

    describe('->awaitable()', function () {
        it('returns $this', function () {
            expect($this->subject->mock()->awaitable())->to->equal($this->subject->mock());
        });
    });

    context('when the strand has succeeded', function () {
        beforeEach(function () {
            ($this->initializeSubject)(
                Phony::stub()->generates()->returns()
            );
            $this->subject->mock()->start();
        });

        it('->start() fails', function () {
            expect(function () {
                $this->subject->mock()->start();
            })->to->throw(
                AssertionError::class,
                'strand must be READY or SUSPENDED to start'
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

        it('->hasExited() returns true', function () {
            expect($this->subject->mock()->hasExited())->to->be->true;
        });

        it('->await() resumes the given strand immediately', function () {
            $strand = Phony::mock(Strand::class);
            $this->subject->mock()->await($strand->mock(), $this->api->mock());
            $strand->resume->calledWith();
        });
    });

    context('when the strand has failed', function () {
        beforeEach(function () {
            ($this->initializeSubject)(
                Phony::stub()->generates()->throws(new Exception('<exception>'))
            );
            $this->subject->mock()->start();
        });

        it('->start() fails', function () {
            expect(function () {
                $this->subject->mock()->start();
            })->to->throw(
                AssertionError::class,
                'strand must be READY or SUSPENDED to start'
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

        it('->hasExited() returns true', function () {
            expect($this->subject->mock()->hasExited())->to->be->true;
        });

        it('->await() resumes the given strand immediately', function () {
            $strand = Phony::mock(Strand::class);
            $this->subject->mock()->await($strand->mock(), $this->api->mock());
            $strand->resume->calledWith();
        });
    });

    context('when the strand has been terminated', function () {
        beforeEach(function () {
            $this->subject->mock()->terminate();
        });

        it('->start() does nothing', function () {
            $this->subject->mock()->start();
            $this->api->noInteraction();
        });

        it('->resume() does nothing', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();
            $this->subject->mock()->terminate();
            $this->subject->mock()->resume('<result>');

            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('->throw() does nothing', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();
            $this->subject->mock()->terminate();
            $exception = Phony::mock(Throwable::class);
            $this->subject->mock()->throw($exception->mock());

            $fn->never()->received();
            $fn->never()->receivedException();
        });

        it('->terminate() does nothing', function () {
            $this->subject->mock()->terminate();
        });

        it('->hasExited() returns true', function () {
            expect($this->subject->mock()->hasExited())->to->be->true;
        });

        it('->await() resumes the given strand immediately', function () {
            $strand = Phony::mock(Strand::class);
            $this->subject->mock()->await($strand->mock(), $this->api->mock());
            $strand->resume->calledWith();
        });
    });

});

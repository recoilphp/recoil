<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use AssertionError;
use Eloquent\Phony\Phony;
use Exception;
use Generator;
use InvalidArgumentException;
use Recoil\Exception\TerminatedException;
use Recoil\Kernel\Exception\PrimaryListenerRemovedException;
use Recoil\Kernel\Exception\StrandListenerException;
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

        $this->primaryListener = Phony::mock(Listener::class);

        $this->listener1 = Phony::mock(Listener::class);
        $this->listener2 = Phony::mock(Listener::class);

        $this->strand1 = Phony::mock(Strand::class);
        $this->strand2 = Phony::mock(Strand::class);
    });

    describe('->__construct()', function () {
        it('accepts a generator object', function () {
            $fn = Phony::stub()->generates(['<key>' => '<value>'])->returns();
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
            $fn->generated()->never()->received();
            $fn->generated()->never()->receivedException();
        });

        it('accepts a generator function', function () {
            $fn = Phony::stub();
            $fn->generates(['<key>' => '<value>']);
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
            $fn->generated()->never()->received();
            $fn->generated()->never()->receivedException();
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

                $fn->generated()->received('<result>');
            });

            context('when the top of the call-stack is reached', function () {
                it('notifies the primary listener', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );
                    $this->subject->mock()->setPrimaryListener($this->primaryListener->mock());
                    $this->subject->mock()->start();

                    $this->primaryListener->send->calledWith('<result>', $this->subject);
                });

                it('notifies the primary listener when set afterwards', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );
                    $this->subject->mock()->start();
                    $this->subject->mock()->setPrimaryListener($this->primaryListener->mock());

                    $this->primaryListener->send->calledWith('<result>', $this->subject);
                });

                it('notifies the kernel when a listener throws', function () {
                    $exception = new Exception('<exception>');
                    $this->primaryListener->send->throws($exception);

                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns()
                    );
                    $this->subject->mock()->setPrimaryListener($this->primaryListener->mock());
                    $this->subject->mock()->start();

                    $this->kernel->throw->calledWith(
                        new StrandListenerException(
                            $this->subject->mock(),
                            $exception
                        ),
                        $this->subject
                    );
                });

                it('notifies secondary listeners', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );
                    $this->subject->mock()->await($this->listener1->mock(), $this->api->mock());
                    $this->subject->mock()->await($this->listener2->mock(), $this->api->mock());
                    $this->subject->mock()->start();

                    $this->listener1->send->calledWith('<result>', $this->subject);
                    $this->listener2->send->calledWith('<result>', $this->subject);
                });

                it('terminates linked strands', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );

                    $this->subject->mock()->link($this->strand1->mock());
                    $this->subject->mock()->link($this->strand2->mock());
                    $this->subject->mock()->start();

                    Phony::inOrder(
                        $this->strand1->unlink->calledWith($this->subject),
                        $this->strand1->terminate->called()
                    );

                    Phony::inOrder(
                        $this->strand2->unlink->calledWith($this->subject),
                        $this->strand2->terminate->called()
                    );
                });

                it('does not terminate unlinked strands', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );

                    $this->subject->mock()->link($this->strand1->mock());
                    $this->subject->mock()->unlink($this->strand1->mock());
                    $this->subject->mock()->start();

                    $this->strand1->terminate->never()->called();
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
                $fn->generated()->receivedException($exception);
            });

            context('when the top of the call-stack is reached', function () {
                it('notifies the primary listener', function () {
                    $exception = new Exception('<exception>');
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($exception)
                    );
                    $this->subject->mock()->setPrimaryListener($this->primaryListener->mock());
                    $this->subject->mock()->start();

                    $this->primaryListener->throw->calledWith(
                        $exception,
                        $this->subject
                    );
                });

                it('notifies the primary listener when set afterwards', function () {
                    $exception = new Exception('<exception>');
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($exception)
                    );
                    $this->subject->mock()->start();
                    $this->subject->mock()->setPrimaryListener($this->primaryListener->mock());

                    $this->primaryListener->throw->calledWith(
                        $exception,
                        $this->subject
                    );
                });

                it('notifies the kernel when a listener throws', function () {
                    $listenerException = new Exception('<listener-exception>');
                    $this->primaryListener->throw->throws($listenerException);
                    $strandException = new Exception('<exception>');

                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($strandException)
                    );
                    $this->subject->mock()->setPrimaryListener($this->primaryListener->mock());
                    $this->subject->mock()->start();

                    $this->kernel->throw->calledWith(
                        new StrandListenerException(
                            $this->subject->mock(),
                            $listenerException
                        ),
                        $this->subject
                    );
                });

                it('resumes waiting strands', function () {
                    $exception = new Exception('<exception>');
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($exception)
                    );
                    $this->subject->mock()->await($this->listener1->mock(), $this->api->mock());
                    $this->subject->mock()->await($this->listener2->mock(), $this->api->mock());
                    $this->subject->mock()->start();

                    $this->listener1->throw->calledWith($exception, $this->subject);
                    $this->listener2->throw->calledWith($exception, $this->subject);
                });

                it('terminates linked strands', function () {
                    $exception = new Exception('<exception>');
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($exception)
                    );

                    $this->subject->mock()->link($this->strand1->mock());
                    $this->subject->mock()->link($this->strand2->mock());
                    $this->subject->mock()->start();

                    Phony::inOrder(
                        $this->strand1->unlink->calledWith($this->subject),
                        $this->strand1->terminate->called()
                    );

                    Phony::inOrder(
                        $this->strand2->unlink->calledWith($this->subject),
                        $this->strand2->terminate->called()
                    );
                });

                it('does not terminate unlinked strands', function () {
                    $exception = new Exception('<exception>');
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($exception)
                    );

                    $this->subject->mock()->link($this->strand1->mock());
                    $this->subject->mock()->unlink($this->strand1->mock());
                    $this->subject->mock()->start();

                    $this->strand1->terminate->never()->called();
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

                $fn->generated()->received('<result>');
            });

            it('dispatches kernel api calls', function () {
                $fn = Phony::stub();
                $fn->generates([new ApiCall('<name>', [1, 2, 3])]);
                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();

                $this->api->{'<name>'}->calledWith($this->subject, 1, 2, 3);
                $fn->generated()->never()->received();
                $fn->generated()->never()->receivedException();
            });

            it('dispatches kernel api calls implemented as coroutines', function () {
                $this->api->{'<name>'}->generates()->returns('<result>');
                $fn = Phony::stub();
                $fn->generates([new ApiCall('<name>', [1, 2, 3])]);
                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();

                $fn->generated()->received('<result>');
            });

            it('attaches the strand to awaitables', function () {
                $awaitable = Phony::mock(Awaitable::class);
                $fn = Phony::stub();
                $fn->generates([$awaitable->mock()]); // @todo https://github.com/eloquent/phony/issues/144
                ($this->initializeSubject)($fn);
                $this->subject->mock()->start();

                $awaitable->await->calledWith($this->subject, $this->api);
                $fn->generated()->never()->received();
                $fn->generated()->never()->receivedException();
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
                $fn->generated()->never()->received();
                $fn->generated()->never()->receivedException();
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

                $fn->generated()->receivedException($exception);
            });
        });
    });

    describe('->send()', function () {
        it('sends the value to the coroutine', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();

            $this->subject->mock()->send('<result>');
            $fn->generated()->received('<result>');
        });

        it('can be invoked from inside ->start()', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            $this->api->dispatch->does(function () {
                $this->subject->mock()->send('<result>');
            });
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();

            $fn->generated()->received('<result>');
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

            $fn->generated()->receivedException($exception);
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

            $fn->generated()->receivedException($exception);
        });
    });

    describe('->terminate()', function () {
        it('invokes the terminator function', function () {
            $fn = Phony::spy();
            $this->subject->mock()->setTerminator($fn);
            $this->subject->mock()->terminate();

            $fn->once()->calledWith($this->subject);
        });

        it('notifies the primary listener', function () {
            $this->subject->mock()->setPrimaryListener($this->primaryListener->mock());
            $this->subject->mock()->terminate();

            $this->primaryListener->throw->once()->calledWith(
                new TerminatedException($this->subject->mock()),
                $this->subject
            );
        });

        it('notifies the primary listener when set afterwards', function () {
            $this->subject->mock()->terminate();
            $this->subject->mock()->setPrimaryListener($this->primaryListener->mock());

            $this->primaryListener->throw->once()->calledWith(
                new TerminatedException($this->subject->mock()),
                $this->subject
            );
        });

        it('resumes waiting strands', function () {
            $this->subject->mock()->await($this->listener1->mock(), $this->api->mock());
            $this->subject->mock()->await($this->listener2->mock(), $this->api->mock());
            $this->subject->mock()->terminate();

            $exception = new TerminatedException($this->subject->mock());
            $this->listener1->throw->calledWith($exception, $this->subject);
            $this->listener2->throw->calledWith($exception, $this->subject);
        });

        it('notifies the kernel when a listener throws', function () {
            $exception = new Exception('<listener-exception>');
            $this->primaryListener->throw->throws($exception);
            $this->subject->mock()->setPrimaryListener($this->primaryListener->mock());
            $this->subject->mock()->terminate();

            $this->kernel->throw->calledWith(
                new StrandListenerException(
                    $this->subject->mock(),
                    $exception
                ),
                $this->subject
            );
        });

        it('terminates linked strands', function () {
            $this->subject->mock()->link($this->strand1->mock());
            $this->subject->mock()->link($this->strand2->mock());
            $this->subject->mock()->terminate();

            Phony::inOrder(
                $this->strand1->unlink->calledWith($this->subject),
                $this->strand1->terminate->called()
            );

            Phony::inOrder(
                $this->strand2->unlink->calledWith($this->subject),
                $this->strand2->terminate->called()
            );
        });

        it('does not terminate unlinked strands', function () {
            $this->subject->mock()->link($this->strand1->mock());
            $this->subject->mock()->unlink($this->strand1->mock());
            $this->subject->mock()->terminate();

            $this->strand1->terminate->never()->called();
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

    describe('->setPrimaryListener()', function () {
        it('notifies the previous listener with an exception', function () {
            $this->subject->mock()->setPrimaryListener($this->listener1->mock());
            $this->subject->mock()->setPrimaryListener($this->listener2->mock());
            $this->listener1->throw->calledWith(
                new PrimaryListenerRemovedException(
                    $this->listener1->mock(),
                    $this->subject->mock()
                ),
                $this->subject->mock()
            );
        });

        it('does not notify the kernel when removed', function () {
            $this->subject->mock()->setPrimaryListener($this->listener1->mock());
            $this->kernel->throw->never()->called();
        });
    });

    context('when the strand has succeeded', function () {
        beforeEach(function () {
            ($this->initializeSubject)(
                Phony::stub()->generates()->returns('<result>')
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

        it('->send() fails', function () {
            expect(function () {
                $this->subject->mock()->send('<result>');
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

        it('->terminate() does nothing', function () {
            $this->subject->mock()->terminate();
        });

        it('->hasExited() returns true', function () {
            expect($this->subject->mock()->hasExited())->to->be->true;
        });

        it('->await() resumes the given strand immediately', function () {
            $strand = Phony::mock(Strand::class);
            $this->subject->mock()->await($strand->mock(), $this->api->mock());
            $strand->send->calledWith('<result>', $this->subject->mock());
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

        it('->send() fails', function () {
            expect(function () {
                $this->subject->mock()->send('<result>');
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

        it('->terminate() does nothing', function () {
            $this->subject->mock()->terminate();
        });

        it('->hasExited() returns true', function () {
            expect($this->subject->mock()->hasExited())->to->be->true;
        });

        it('->await() resumes the given strand immediately', function () {
            $strand = Phony::mock(Strand::class);
            $this->subject->mock()->await($strand->mock(), $this->api->mock());
            $strand->throw->calledWith(new Exception('<exception>'), $this->subject->mock());
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

        it('->send() does nothing', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();
            $this->subject->mock()->terminate();
            $this->subject->mock()->send('<result>');

            $fn->generated()->never()->received();
            $fn->generated()->never()->receivedException();
        });

        it('->throw() does nothing', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->mock()->start();
            $this->subject->mock()->terminate();
            $exception = Phony::mock(Throwable::class);
            $this->subject->mock()->throw($exception->mock());

            $fn->generated()->never()->received();
            $fn->generated()->never()->receivedException();
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
            $strand->throw->calledWith(
                new TerminatedException($this->subject->mock()),
                $this->subject->mock()
            );
        });
    });

});

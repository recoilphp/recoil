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
                    $this->kernel->get(),
                    $this->api->get(),
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
            $this->subject->get()->start();

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
            $fn->generated()->never()->received();
            $fn->generated()->never()->receivedException();
        });

        it('accepts a generator function', function () {
            $fn = Phony::stub();
            $fn->generates(['<key>' => '<value>']);
            ($this->initializeSubject)($fn);
            $this->subject->get()->start();

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
            $fn->generated()->never()->received();
            $fn->generated()->never()->receivedException();
        });

        it('accepts a coroutine provider', function () {
            $provider = Phony::mock(CoroutineProvider::class);
            $provider->coroutine->generates(['<key>' => '<value>']);
            ($this->initializeSubject)($provider->get());
            $this->subject->get()->start();

            $this->api->dispatch->calledWith($this->subject, '<key>', '<value>');
        });

        it('throws when passed a regular function', function () {
            try {
                ($this->initializeSubject)(function () {});
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (InvalidArgumentException $e) {
                expect($e->getMessage())->to->equal('Callable must return a generator.');
            }
        });

        it('dispatches other types via the kernel api', function () {
            ($this->initializeSubject)('<value>');
            $this->subject->get()->start();
            $this->api->dispatch->calledWith($this->subject, 0, '<value>');
        });
    });

    describe('->id()', function () {
        it('returns the ID that was passed to the constructor', function () {
            expect($this->subject->get()->id())->to->equal(123);
        });
    });

    describe('->kernel()', function () {
        it('returns the kernel that was passed to the constructor', function () {
            expect($this->subject->get()->kernel())->to->equal($this->kernel->get());
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
                $this->subject->get()->start();

                $fn->generated()->received('<result>');
            });

            context('when the top of the call-stack is reached', function () {
                it('notifies the primary listener', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );
                    $this->subject->get()->setPrimaryListener($this->primaryListener->get());
                    $this->subject->get()->start();

                    $this->primaryListener->send->calledWith('<result>', $this->subject);
                });

                it('notifies the primary listener when set afterwards', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );
                    $this->subject->get()->start();
                    $this->subject->get()->setPrimaryListener($this->primaryListener->get());

                    $this->primaryListener->send->calledWith('<result>', $this->subject);
                });

                it('notifies the kernel when a listener throws', function () {
                    $exception = new Exception('<exception>');
                    $this->primaryListener->send->throws($exception);

                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns()
                    );
                    $this->subject->get()->setPrimaryListener($this->primaryListener->get());
                    $this->subject->get()->start();

                    $this->kernel->throw->calledWith(
                        new StrandListenerException(
                            $this->subject->get(),
                            $exception
                        ),
                        $this->subject
                    );
                });

                it('notifies secondary listeners', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );
                    $this->subject->get()->await($this->listener1->get(), $this->api->get());
                    $this->subject->get()->await($this->listener2->get(), $this->api->get());
                    $this->subject->get()->start();

                    $this->listener1->send->calledWith('<result>', $this->subject);
                    $this->listener2->send->calledWith('<result>', $this->subject);
                });

                it('terminates linked strands', function () {
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->returns('<result>')
                    );

                    $this->subject->get()->link($this->strand1->get());
                    $this->subject->get()->link($this->strand2->get());
                    $this->subject->get()->start();

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

                    $this->subject->get()->link($this->strand1->get());
                    $this->subject->get()->unlink($this->strand1->get());
                    $this->subject->get()->start();

                    $this->strand1->terminate->never()->called();
                });
            });
        });

        context('when a coroutine throws an exception', function () {
            it('propagates the exception up the call-stack', function () {
                $exception = Phony::mock(Throwable::class);
                $fn = Phony::spy(function () use ($exception) {
                    yield (function () use ($exception) {
                        throw $exception->get();
                        yield;
                    })();
                });

                ($this->initializeSubject)($fn);
                $this->subject->get()->start();
                $fn->generated()->receivedException($exception);
            });

            context('when the top of the call-stack is reached', function () {
                it('notifies the primary listener', function () {
                    $exception = new Exception('<exception>');
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($exception)
                    );
                    $this->subject->get()->setPrimaryListener($this->primaryListener->get());
                    $this->subject->get()->start();

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
                    $this->subject->get()->start();
                    $this->subject->get()->setPrimaryListener($this->primaryListener->get());

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
                    $this->subject->get()->setPrimaryListener($this->primaryListener->get());
                    $this->subject->get()->start();

                    $this->kernel->throw->calledWith(
                        new StrandListenerException(
                            $this->subject->get(),
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
                    $this->subject->get()->await($this->listener1->get(), $this->api->get());
                    $this->subject->get()->await($this->listener2->get(), $this->api->get());
                    $this->subject->get()->start();

                    $this->listener1->throw->calledWith($exception, $this->subject);
                    $this->listener2->throw->calledWith($exception, $this->subject);
                });

                it('terminates linked strands', function () {
                    $exception = new Exception('<exception>');
                    ($this->initializeSubject)(
                        Phony::stub()->generates()->throws($exception)
                    );

                    $this->subject->get()->link($this->strand1->get());
                    $this->subject->get()->link($this->strand2->get());
                    $this->subject->get()->start();

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

                    $this->subject->get()->link($this->strand1->get());
                    $this->subject->get()->unlink($this->strand1->get());
                    $this->subject->get()->start();

                    $this->strand1->terminate->never()->called();
                });
            });
        });

        context('when a coroutine yields', function () {
            it('invokes coroutines from coroutine providers', function () {
                $provider = Phony::mock(CoroutineProvider::class);
                $provider->coroutine->generates()->returns('<result>');
                $fn = Phony::stub();
                $fn->generates([$provider->get()]); // @todo https://github.com/eloquent/phony/issues/144
                ($this->initializeSubject)($fn);
                $this->subject->get()->start();

                $fn->generated()->received('<result>');
            });

            it('dispatches kernel api calls', function () {
                $fn = Phony::stub();
                $fn->generates([new ApiCall('<name>', [1, 2, 3])]);
                ($this->initializeSubject)($fn);
                $this->subject->get()->start();

                $this->api->{'<name>'}->calledWith($this->subject, 1, 2, 3);
                $fn->generated()->never()->received();
                $fn->generated()->never()->receivedException();
            });

            it('dispatches kernel api calls implemented as coroutines', function () {
                $this->api->{'<name>'}->generates()->returns('<result>');
                $fn = Phony::stub();
                $fn->generates([new ApiCall('<name>', [1, 2, 3])]);
                ($this->initializeSubject)($fn);
                $this->subject->get()->start();

                $fn->generated()->received('<result>');
            });

            it('attaches the strand to awaitables', function () {
                $awaitable = Phony::mock(Awaitable::class);
                $fn = Phony::stub();
                $fn->generates([$awaitable->get()]); // @todo https://github.com/eloquent/phony/issues/144
                ($this->initializeSubject)($fn);
                $this->subject->get()->start();

                $awaitable->await->calledWith($this->subject, $this->api);
                $fn->generated()->never()->received();
                $fn->generated()->never()->receivedException();
            });

            it('attaches the strand to awaitables from awaitable providers', function () {
                $awaitable = Phony::mock(Awaitable::class);
                $provider = Phony::mock(AwaitableProvider::class);
                $provider->awaitable->returns($awaitable);
                $fn = Phony::stub();
                $fn->generates([$provider->get()]); // @todo https://github.com/eloquent/phony/issues/144
                ($this->initializeSubject)($fn);
                $this->subject->get()->start();

                $awaitable->await->calledWith($this->subject, $this->api);
                $fn->generated()->never()->received();
                $fn->generated()->never()->receivedException();
            });

            it('forwards other values to the api for dispatch', function () {
                ($this->initializeSubject)(
                    Phony::stub()->generates(['<value>'])->returns()
                );
                $this->subject->get()->start();

                $this->api->dispatch->calledWith($this->subject, 0, '<value>');
            });

            it('propagates exceptions thrown during handling of the yielded value', function () {
                $exception = Phony::mock(Throwable::class);
                $this->api->dispatch->throws($exception);
                $fn = Phony::stub()->generates([null])->returns();
                ($this->initializeSubject)($fn);
                $this->subject->get()->start();

                $fn->generated()->receivedException($exception);
            });
        });
    });

    describe('->send()', function () {
        it('sends the value to the coroutine', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->get()->start();

            $this->subject->get()->send('<result>');
            $fn->generated()->received('<result>');
        });

        it('can be invoked from inside ->start()', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            $this->api->dispatch->does(function () {
                $this->subject->get()->send('<result>');
            });
            ($this->initializeSubject)($fn);
            $this->subject->get()->start();

            $fn->generated()->received('<result>');
        });
    });

    describe('->throw()', function () {
        it('throws the exception to the coroutine', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->get()->start();
            $exception = Phony::mock(Throwable::class);
            $this->subject->get()->throw($exception->get());

            $fn->generated()->receivedException($exception);
        });

        it('can be invoked from inside ->start()', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            $exception = Phony::mock(Throwable::class);
            $this->api->dispatch->does(function () use ($exception) {
                $this->subject->get()->throw($exception->get());
            });
            ($this->initializeSubject)($fn);
            $this->subject->get()->start();

            $fn->generated()->receivedException($exception);
        });
    });

    describe('->terminate()', function () {
        it('invokes the terminator function', function () {
            $fn = Phony::spy();
            $this->subject->get()->setTerminator($fn);
            $this->subject->get()->terminate();

            $fn->once()->calledWith($this->subject);
        });

        it('notifies the primary listener', function () {
            $this->subject->get()->setPrimaryListener($this->primaryListener->get());
            $this->subject->get()->terminate();

            $this->primaryListener->throw->once()->calledWith(
                new TerminatedException($this->subject->get()),
                $this->subject
            );
        });

        it('notifies the primary listener when set afterwards', function () {
            $this->subject->get()->terminate();
            $this->subject->get()->setPrimaryListener($this->primaryListener->get());

            $this->primaryListener->throw->once()->calledWith(
                new TerminatedException($this->subject->get()),
                $this->subject
            );
        });

        it('resumes waiting strands', function () {
            $this->subject->get()->await($this->listener1->get(), $this->api->get());
            $this->subject->get()->await($this->listener2->get(), $this->api->get());
            $this->subject->get()->terminate();

            $exception = new TerminatedException($this->subject->get());
            $this->listener1->throw->calledWith($exception, $this->subject);
            $this->listener2->throw->calledWith($exception, $this->subject);
        });

        it('notifies the kernel when a listener throws', function () {
            $exception = new Exception('<listener-exception>');
            $this->primaryListener->throw->throws($exception);
            $this->subject->get()->setPrimaryListener($this->primaryListener->get());
            $this->subject->get()->terminate();

            $this->kernel->throw->calledWith(
                new StrandListenerException(
                    $this->subject->get(),
                    $exception
                ),
                $this->subject
            );
        });

        it('terminates linked strands', function () {
            $this->subject->get()->link($this->strand1->get());
            $this->subject->get()->link($this->strand2->get());
            $this->subject->get()->terminate();

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
            $this->subject->get()->link($this->strand1->get());
            $this->subject->get()->unlink($this->strand1->get());
            $this->subject->get()->terminate();

            $this->strand1->terminate->never()->called();
        });
    });

    describe('->hasExited()', function () {
        it('returns false', function () {
            expect($this->subject->get()->hasExited())->to->be->false;
        });
    });

    describe('->awaitable()', function () {
        it('returns $this', function () {
            expect($this->subject->get()->awaitable())->to->equal($this->subject->get());
        });
    });

    describe('->setPrimaryListener()', function () {
        it('notifies the previous listener with an exception', function () {
            $this->subject->get()->setPrimaryListener($this->listener1->get());
            $this->subject->get()->setPrimaryListener($this->listener2->get());
            $this->listener1->throw->calledWith(
                new PrimaryListenerRemovedException(
                    $this->listener1->get(),
                    $this->subject->get()
                ),
                $this->subject->get()
            );
        });

        it('does not notify the kernel when removed', function () {
            $this->subject->get()->setPrimaryListener($this->listener1->get());
            $this->kernel->throw->never()->called();
        });
    });

    context('when the strand has succeeded', function () {
        beforeEach(function () {
            ($this->initializeSubject)(
                Phony::stub()->generates()->returns('<result>')
            );
            $this->subject->get()->start();
        });

        it('->start() fails', function () {
            try {
                $this->subject->get()->start();
                assert(false, 'expected exception was not thrown');
            } catch (AssertionError $e) {
                expect($e->getMessage())->to->equal('strand must be READY or SUSPENDED_INACTIVE to start');
            }
        });

        it('->send() fails', function () {
            try {
                $this->subject->get()->send('<result>');
                assert(false, 'expected exception was not thrown');
            } catch (AssertionError $e) {
                expect($e->getMessage())->to->equal('strand must be suspended to resume');
            }
        });

        it('->throw() fails', function () {
            try {
                $exception = Phony::mock(Throwable::class);
                $this->subject->get()->throw($exception->get());
                assert(false, 'expected exception was not thrown');
            } catch (AssertionError $e) {
                expect($e->getMessage())->to->equal('strand must be suspended to resume');
            }
        });

        it('->terminate() does nothing', function () {
            $this->subject->get()->terminate();
        });

        it('->hasExited() returns true', function () {
            expect($this->subject->get()->hasExited())->to->be->true;
        });

        it('->await() resumes the given strand immediately', function () {
            $strand = Phony::mock(Strand::class);
            $this->subject->get()->await($strand->get(), $this->api->get());
            $strand->send->calledWith('<result>', $this->subject->get());
        });
    });

    context('when the strand has failed', function () {
        beforeEach(function () {
            ($this->initializeSubject)(
                Phony::stub()->generates()->throws(new Exception('<exception>'))
            );
            $this->subject->get()->start();
        });

        it('->start() fails', function () {
            try {
                $this->subject->get()->start();
                assert(false, 'expected exception was not thrown');
            } catch (AssertionError $e) {
                expect($e->getMessage())->to->equal('strand must be READY or SUSPENDED_INACTIVE to start');
            }
        });

        it('->send() fails', function () {
            try {
                $this->subject->get()->send('<result>');
                assert(false, 'expected exception was not thrown');
            } catch (AssertionError $e) {
                expect($e->getMessage())->to->equal('strand must be suspended to resume');
            }
        });

        it('->throw() fails', function () {
            try {
                $exception = Phony::mock(Throwable::class);
                $this->subject->get()->throw($exception->get());
                assert(false, 'expected exception was not thrown');
            } catch (AssertionError $e) {
                expect($e->getMessage())->to->equal('strand must be suspended to resume');
            }
        });

        it('->terminate() does nothing', function () {
            $this->subject->get()->terminate();
        });

        it('->hasExited() returns true', function () {
            expect($this->subject->get()->hasExited())->to->be->true;
        });

        it('->await() resumes the given strand immediately', function () {
            $strand = Phony::mock(Strand::class);
            $this->subject->get()->await($strand->get(), $this->api->get());
            $strand->throw->calledWith(new Exception('<exception>'), $this->subject->get());
        });
    });

    context('when the strand has been terminated', function () {
        beforeEach(function () {
            $this->subject->get()->terminate();
        });

        it('->start() does nothing', function () {
            $this->subject->get()->start();
            $this->api->noInteraction();
        });

        it('->send() does nothing', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->get()->start();
            $this->subject->get()->terminate();
            $this->subject->get()->send('<result>');

            $fn->generated()->never()->received();
            $fn->generated()->never()->receivedException();
        });

        it('->throw() does nothing', function () {
            $fn = Phony::stub();
            $fn->generates([null]);
            ($this->initializeSubject)($fn);
            $this->subject->get()->start();
            $this->subject->get()->terminate();
            $exception = Phony::mock(Throwable::class);
            $this->subject->get()->throw($exception->get());

            $fn->generated()->never()->received();
            $fn->generated()->never()->receivedException();
        });

        it('->terminate() does nothing', function () {
            $this->subject->get()->terminate();
        });

        it('->hasExited() returns true', function () {
            expect($this->subject->get()->hasExited())->to->be->true;
        });

        it('->await() resumes the given strand immediately', function () {
            $strand = Phony::mock(Strand::class);
            $this->subject->get()->await($strand->get(), $this->api->get());
            $strand->throw->calledWith(
                new TerminatedException($this->subject->get()),
                $this->subject->get()
            );
        });
    });

    describe('->link()', function () {
        it('can be called if strands are already linked', function () {
            $this->subject->get()->link($this->strand1->get());
            $this->subject->get()->link($this->strand1->get());
        });
    });

    describe('->unlink()', function () {
        it('can be called if strands are not already linked', function () {
            $this->subject->get()->unlink($this->strand1->get());
        });
    });

});

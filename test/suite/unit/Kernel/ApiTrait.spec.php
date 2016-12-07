<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use BadMethodCallException;
use Eloquent\Phony\Phony;
use Error;
use Hamcrest\Core\IsInstanceOf;
use InvalidArgumentException;
use Recoil\Kernel\Exception\RejectedException;
use Throwable;
use UnexpectedValueException;

describe(ApiTrait::class, function () {
    beforeEach(function () {
        $this->kernel = Phony::mock(SystemKernel::class);
        $this->strand = Phony::mock(SystemStrand::class);
        $this->strand->kernel->returns($this->kernel);

        $this->substrand1 = Phony::mock(SystemStrand::class);
        $this->substrand2 = Phony::mock(SystemStrand::class);
        $this->kernel->execute->returns(
            $this->substrand1,
            $this->substrand2
        );

        $this->subject = Phony::partialMock([Api::class, ApiTrait::class]);
    });

    describe('->__dispatch()', function () {
        it('performs ->cooperate() when $value is null', function () {
            $this->subject->get()->__dispatch(
                $this->strand->get(),
                0, // current generator key
                null
            );

            $this->subject->cooperate->calledWith($this->strand);
            $this->strand->noInteraction();
        });

        it('performs ->sleep($value) when $value is an integer', function () {
            $this->subject->get()->__dispatch(
                $this->strand->get(),
                0, // current generator key
                10
            );

            $this->subject->sleep->calledWith(
                $this->strand,
                10.0
            );

            $this->strand->noInteraction();
        });

        it('performs ->sleep($value) when $value is a float', function () {
            $this->subject->get()->__dispatch(
                $this->strand->get(),
                0, // current generator key
                10.5
            );

            $this->subject->sleep->calledWith(
                $this->strand,
                10.5
            );

            $this->strand->noInteraction();
        });

        it('performs ->all(...$value) when $value is an array', function () {
            $this->subject->all->returns(null);

            $this->subject->get()->__dispatch(
                $this->strand->get(),
                0, // current generator key
                ['<a>', '<b>']
            );

            $this->subject->all->calledWith(
                $this->strand,
                '<a>',
                '<b>'
            );

            $this->strand->noInteraction();
        });

        context('when $value is a resource', function () {
            beforeEach(function () {
                $this->resource = fopen('php://memory', 'r+');
            });

            afterEach(function () {
                fclose($this->resource);
            });

            it('reads from the stream if $key is an integer (the default)', function () {
                $this->subject->get()->__dispatch(
                    $this->strand->get(),
                    123,
                    $this->resource
                );

                $this->subject->read->calledWith(
                    $this->strand,
                    $this->resource,
                    1,
                    PHP_INT_MAX
                );
            });

            it('writes to the stream if $key is a string', function () {
                $this->subject->get()->__dispatch(
                    $this->strand->get(),
                    '<buffer>',
                    $this->resource
                );

                $this->subject->write->calledWith(
                    $this->strand,
                    $this->resource,
                    '<buffer>',
                    PHP_INT_MAX
                );
            });
        });

        context('when $value is thenable', function () {
            beforeEach(function () {
                $this->thenable = Phony::mock(
                    ['function then' => null]
                );

                $this->subject->get()->__dispatch(
                    $this->strand->get(),
                    0, // current generator key
                    $this->thenable->get()
                );

                list($this->resolve, $this->reject) = $this->thenable->then->calledWith(
                    '~',
                    '~'
                )->firstCall()->arguments()->all();

                expect($this->resolve)->to->satisfy('is_callable');
                expect($this->reject)->to->satisfy('is_callable');

                $this->strand->noInteraction();
            });

            it('resumes the strand when resolved', function () {
                ($this->resolve)('<result>');
                $this->strand->send->calledWith('<result>');
            });

            it('resumes the strand with an exception when rejected', function () {
                $exception = Phony::mock(Throwable::class);
                ($this->reject)($exception->get());
                $this->strand->throw->calledWith($exception);
            });

            it('resumes the strand with an exception when rejected with a non-exception', function () {
                ($this->reject)('<reason>');
                $this->strand->throw->calledWith(new RejectedException('<reason>'));
            });
        });

        context('when $value is thenable and doneable', function () {
            beforeEach(function () {
                $this->thenable = Phony::mock(
                    [
                        'function then' => null,
                        'function done' => null,
                    ]
                );

                $this->subject->get()->__dispatch(
                    $this->strand->get(),
                    0, // current generator key
                    $this->thenable->get()
                );

                list($this->resolve, $this->reject) = $this->thenable->done->calledWith(
                    '~',
                    '~'
                )->firstCall()->arguments()->all();

                $this->thenable->then->never()->called();

                expect($this->resolve)->to->satisfy('is_callable');
                expect($this->reject)->to->satisfy('is_callable');

                $this->strand->noInteraction();
            });

            it('resumes the strand when resolved', function () {
                ($this->resolve)('<result>');
                $this->strand->send->calledWith('<result>');
            });

            it('resumes the strand with an exception when rejected', function () {
                $exception = Phony::mock(Throwable::class);
                ($this->reject)($exception->get());
                $this->strand->throw->calledWith($exception);
            });

            it('resumes the strand with an exception when rejected with a non-exception', function () {
                ($this->reject)('<reason>');
                $this->strand->throw->calledWith(new RejectedException('<reason>'));
            });
        });

        context('when $value is thenable and cancellable', function () {
            beforeEach(function () {
                $this->thenable = Phony::mock(
                    [
                        'function then' => null,
                        'function cancel' => null,
                    ]
                );

                $this->subject->get()->__dispatch(
                    $this->strand->get(),
                    0, // current generator key
                    $this->thenable->get()
                );
            });

            it('cancels the thenable when the strand is terminated', function () {
                $terminator = $this->strand->setTerminator->calledWith('~')->firstCall()->argument();
                expect($terminator)->to->satisfy('is_callable');
                $this->thenable->cancel->never()->called();
                $terminator();
                $this->thenable->cancel->called();
            });
        });

        it('resumes the strand with an exception if $value is not actionable', function () {
            $this->subject->get()->__dispatch(
                $this->strand->get(),
                123, // current generator key
                '<string>'
            );

            $this->strand->throw->calledWith(
                new UnexpectedValueException(
                    'The yielded pair (123, "<string>") does not describe any known operation.'
                )
            );
        });
    });

    describe('->__call()', function () {
        it('resumes the strand with an exception', function () {
            $this->subject->get()->unknown(
                $this->strand->get()
            );

            $this->strand->throw->calledWith(
                new BadMethodCallException(
                    'The API does not implement an operation named "unknown".'
                )
            );
        });

        it('throws when no strand is passed', function () {
            try {
                $this->subject->get()->unknown();
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (Error $e) {
                // okay
            }
        });
    });

    describe('->execute()', function () {
        beforeEach(function () {
            $this->subject->get()->execute(
                $this->strand->get(),
                '<coroutine>'
            );
        });

        it('executes the coroutine', function () {
            $this->kernel->execute->calledWith('<coroutine>');
        });

        it('resumes the strand with the substrand', function () {
            $this->strand->send->calledWith($this->substrand1);
        });
    });

    describe('->callback()', function () {
        it('resumes the strand with a callback that executes the coroutine', function () {
            $coroutine = Phony::stub()->returns('<result>');

            $this->subject->get()->callback(
                $this->strand->get(),
                $coroutine
            );

            $fn = $this
                ->strand
                ->send
                ->calledWith('~')
                ->firstCall()
                ->argument();

            expect($fn)->to->satisfy('is_callable');

            $this->kernel->execute->never()->called();

            $fn(1, 2, 3);

            $coroutine->calledWith(1, 2, 3);
            $this->kernel->execute->calledWith('<result>');
        });
    });

    describe('->strand()', function () {
        it('resumes the strand with itself', function () {
            $this->subject->get()->strand(
                $this->strand->get()
            );

            $this->strand->send->calledWith($this->strand);
        });
    });

    describe('->suspend()', function () {
        it('does not resume the strand', function () {
            $this->subject->get()->suspend(
                $this->strand->get()
            );

            $this->strand->noInteraction();
        });

        it('passes the strand to the callback parameter, if provided', function () {
            $fn = Phony::spy();

            $this->subject->get()->suspend(
                $this->strand->get(),
                $fn
            );

            $fn->calledWith($this->strand);
            $this->strand->noInteraction();
        });

        it('sets the terminator callback, if provided', function () {
            $fn = Phony::spy();

            $this->subject->get()->suspend(
                $this->strand->get(),
                null,
                $fn
            );

            $fn->never()->called();
            $this->strand->setTerminator->calledWith($fn);
        });
    });

    describe('->terminate()', function () {
        it('terminates the strand', function () {
            $this->subject->get()->terminate(
                $this->strand->get()
            );

            $this->strand->terminate->called();
        });
    });

    describe('->stop()', function () {
        it('stops the kernel', function () {
            $this->subject->get()->stop(
                $this->strand->get()
            );

            $this->kernel->stop->called();
        });
    });

    describe('->link()', function () {
        it('links both strands', function () {
            $this->subject->get()->link(
                $this->strand->get(),
                $this->substrand1->get(),
                $this->substrand2->get()
            );

            Phony::inOrder(
                $this->substrand1->link->calledWith($this->substrand2),
                $this->substrand2->link->calledWith($this->substrand1),
                $this->strand->send->called()
            );
        });

        it('uses the current strand if the second strand is not provided', function () {
            $this->subject->get()->link(
                $this->strand->get(),
                $this->substrand1->get()
            );

            Phony::inOrder(
                $this->substrand1->link->calledWith($this->strand),
                $this->strand->link->calledWith($this->substrand1),
                $this->strand->send->called()
            );
        });
    });

    describe('->unlink()', function () {
        it('links both strands', function () {
            $this->subject->get()->unlink(
                $this->strand->get(),
                $this->substrand1->get(),
                $this->substrand2->get()
            );

            Phony::inOrder(
                $this->substrand1->unlink->calledWith($this->substrand2),
                $this->substrand2->unlink->calledWith($this->substrand1),
                $this->strand->send->called()
            );
        });

        it('uses the current strand if the second strand is not provided', function () {
            $this->subject->get()->unlink(
                $this->strand->get(),
                $this->substrand1->get()
            );

            Phony::inOrder(
                $this->substrand1->unlink->calledWith($this->strand),
                $this->strand->unlink->calledWith($this->substrand1),
                $this->strand->send->called()
            );
        });
    });

    describe('->adopt()', function () {
        it('sets the substrands primary listener to the current strand', function () {
            $this->subject->get()->adopt(
                $this->strand->get(),
                $this->substrand1->get()
            );

            $this->substrand1->setPrimaryListener->calledWith($this->strand);
        });

        it('terminates the substrand when the current strand is terminated', function () {
            $this->subject->get()->adopt(
                $this->strand->get(),
                $this->substrand1->get()
            );

            $terminator = $this->strand->setTerminator->calledWith('~')->firstCall()->argument();
            expect($terminator)->to->satisfy('is_callable');

            $this->substrand1->terminate->never()->called();
            $terminator();

            Phony::inOrder(
                $this->substrand1->clearPrimaryLIstener->called(),
                $this->substrand1->terminate->called()
            );
        });
    });

    describe('->all()', function () {
        it('attaches a StrandWaitAll instance to the substrands', function () {
            $this->subject->get()->all(
                $this->strand->get(),
                '<a>',
                '<b>'
            );

            $this->kernel->execute->calledWith('<a>');
            $this->kernel->execute->calledWith('<b>');

            Phony::inOrder(
                $call1 = $this->substrand1->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitAll::class)
                )->firstCall(),
                $call2 = $this->substrand2->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitAll::class)
                )->firstCall()
            );

            expect($call1->argument())->to->equal($call2->argument());
        });
    });

    describe('->any()', function () {
        it('attaches a StrandWaitAny instance to the substrands', function () {
            $this->subject->get()->any(
                $this->strand->get(),
                '<a>',
                '<b>'
            );

            $this->kernel->execute->calledWith('<a>');
            $this->kernel->execute->calledWith('<b>');

            Phony::inOrder(
                $call1 = $this->substrand1->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitAny::class)
                )->firstCall(),
                $call2 = $this->substrand2->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitAny::class)
                )->firstCall()
            );

            expect($call1->argument())->to->equal($call2->argument());
        });
    });

    describe('->some()', function () {
        it('attaches a StrandWaitSome instance to the substrands', function () {
            $this->subject->get()->some(
                $this->strand->get(),
                1,
                '<a>',
                '<b>'
            );

            $this->kernel->execute->calledWith('<a>');
            $this->kernel->execute->calledWith('<b>');

            Phony::inOrder(
                $call1 = $this->substrand1->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitSome::class)
                )->firstCall(),
                $call2 = $this->substrand2->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitSome::class)
                )->firstCall()
            );

            expect($call1->argument()->count())->to->equal(1);
            expect($call1->argument())->to->equal($call2->argument());

            $this->strand->throw->never()->called();
        });

        it('resumes the strand with an exception if the count is less than one', function () {
            $this->subject->get()->some(
                $this->strand->get(),
                0,
                '<a>',
                '<b>'
            );

            $this->strand->throw->calledWith(
                new InvalidArgumentException(
                    'Can not wait for 0 coroutines, count must be between 1 and 2, inclusive.'
                )
            );
        });

        it('resumes the strand with an exception if the count is greater than the number of substrands', function () {
            $this->subject->get()->some(
                $this->strand->get(),
                3,
                '<a>',
                '<b>'
            );

            $this->strand->throw->calledWith(
                new InvalidArgumentException(
                    'Can not wait for 3 coroutines, count must be between 1 and 2, inclusive.'
                )
            );
        });
    });

    describe('->first()', function () {
        it('attaches a StrandWaitFirst instance to the substrands', function () {
            $this->subject->get()->first(
                $this->strand->get(),
                '<a>',
                '<b>'
            );

            $this->kernel->execute->calledWith('<a>');
            $this->kernel->execute->calledWith('<b>');

            Phony::inOrder(
                $call1 = $this->substrand1->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitFirst::class)
                )->firstCall(),
                $call2 = $this->substrand2->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitFirst::class)
                )->firstCall()
            );

            expect($call1->argument())->to->equal($call2->argument());
        });
    });
});

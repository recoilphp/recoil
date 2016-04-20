<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use BadMethodCallException;
use Eloquent\Phony\Phony;
use Hamcrest\Core\IsInstanceOf;
use InvalidArgumentException;
use Recoil\Exception\RejectedException;
use Throwable;
use TypeError;
use UnexpectedValueException;

describe(ApiTrait::class, function () {

    beforeEach(function () {
        $this->kernel = Phony::mock(Kernel::class);
        $this->strand = Phony::mock(Strand::class);
        $this->strand->kernel->returns($this->kernel);

        $this->substrand1 = Phony::mock(Strand::class);
        $this->substrand2 = Phony::mock(Strand::class);
        $this->kernel->execute->returns(
            $this->substrand1,
            $this->substrand2
        );

        $this->subject = Phony::partialMock([Api::class, ApiTrait::class]);
    });

    describe('->dispatch()', function () {

        it('performs ->cooperate() when $value is null', function () {
            $this->subject->mock()->dispatch(
                $this->strand->mock(),
                0, // current generator key
                null
            );

            $this->subject->cooperate->calledWith($this->strand);
            $this->strand->noInteraction();
        });

        it('performs ->sleep($value) when $value is an integer', function () {
            $this->subject->mock()->dispatch(
                $this->strand->mock(),
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
            $this->subject->mock()->dispatch(
                $this->strand->mock(),
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

            $this->subject->mock()->dispatch(
                $this->strand->mock(),
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
                $this->subject->mock()->dispatch(
                    $this->strand->mock(),
                    123,
                    $this->resource
                );

                $this->subject->read->calledWith(
                    $this->strand,
                    $this->resource,
                    1
                );
            });

            it('writes to the stream if $key is a string', function () {
                $this->subject->mock()->dispatch(
                    $this->strand->mock(),
                    '<buffer>',
                    $this->resource
                );

                $this->subject->write->calledWith(
                    $this->strand,
                    $this->resource,
                    '<buffer>'
                );
            });
        });

        context('when $value is thennable', function () {
            beforeEach(function () {
                $this->thennable = Phony::mock(
                    ['function then' => null]
                );

                $this->subject->mock()->dispatch(
                    $this->strand->mock(),
                    0, // current generator key
                    $this->thennable->mock()
                );

                list($this->resolve, $this->reject) = $this->thennable->then->calledWith(
                    '~',
                    '~'
                )->arguments()->all();

                expect($this->resolve)->to->satisfy('is_callable');
                expect($this->reject)->to->satisfy('is_callable');

                $this->strand->noInteraction();
            });

            it('resumes the strand when resolved', function () {
                ($this->resolve)('<result>');
                $this->strand->resume->calledWith('<result>');
            });

            it('resumes the strand with an exception when rejected', function () {
                $exception = Phony::mock(Throwable::class);
                ($this->reject)($exception->mock());
                $this->strand->throw->calledWith($exception);
            });

            it('resumes the strand with an exception when rejected with a non-exception', function () {
                ($this->reject)('<reason>');
                $this->strand->throw->calledWith(new RejectedException('<reason>'));
            });
        });

        context('when $value is thennable and cancellable', function () {
            beforeEach(function () {
                $this->thennable = Phony::mock(
                    [
                        'function then' => null,
                        'function cancel' => null,
                    ]
                );

                $this->subject->mock()->dispatch(
                    $this->strand->mock(),
                    0, // current generator key
                    $this->thennable->mock()
                );
            });

            it('cancels the thennable when the strand is terminated', function () {
                $terminator = $this->strand->setTerminator->calledWith('~')->argument();
                expect($terminator)->to->satisfy('is_callable');
                $this->thennable->cancel->never()->called();
                $terminator();
                $this->thennable->cancel->called();
            });
        });

        it('resumes the strand with an exception if $value is not actionable', function () {
            $this->subject->mock()->dispatch(
                $this->strand->mock(),
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
            $this->subject->mock()->unknown(
                $this->strand->mock()
            );

            $this->strand->throw->calledWith(
                new BadMethodCallException(
                    'The API does not implement an operation named "unknown".'
                )
            );
        });

        it('throws when no strand is passed', function () {
            expect(function () {
                $this->subject->mock()->unknown();
            })->to->throw(TypeError::class);
        });
    });

    describe('->execute()', function () {
        beforeEach(function () {
            $this->subject->mock()->execute(
                $this->strand->mock(),
                '<coroutine>'
            );
        });

        it('executes the coroutine', function () {
            $this->kernel->execute->calledWith('<coroutine>');
        });

        it('resumes the strand with the substrand', function () {
            $this->strand->resume->calledWith($this->substrand1);
        });
    });

    describe('->callback()', function () {
        it('resumes the strand with a callback that executes the coroutine', function () {
            $this->subject->mock()->callback(
                $this->strand->mock(),
                '<coroutine>'
            );

            $fn = $this->strand->resume->calledWith('~')->argument();
            expect($fn)->to->satisfy('is_callable');

            $this->kernel->execute->never()->called();

            $fn();

            $this->kernel->execute->calledWith('<coroutine>');
        });
    });

    describe('->strand()', function () {
        it('resumes the strand with itself', function () {
            $this->subject->mock()->strand(
                $this->strand->mock()
            );

            $this->strand->resume->calledWith($this->strand);
        });
    });

    describe('->suspend()', function () {
        it('does not resume the strand', function () {
            $this->subject->mock()->suspend(
                $this->strand->mock()
            );

            $this->strand->noInteraction();
        });

        it('passes the strand to the callback parameter, if provided', function () {
            $fn = Phony::spy();

            $this->subject->mock()->suspend(
                $this->strand->mock(),
                $fn
            );

            $fn->calledWith($this->strand);
            $this->strand->noInteraction();
        });
    });

    describe('->terminate()', function () {
        it('terminates the strand', function () {
            $this->subject->mock()->terminate(
                $this->strand->mock()
            );

            $this->strand->terminate->called();
        });
    });

    describe('->all()', function () {
        it('attaches a StrandWaitAll instance to the substrands', function () {
            $this->subject->mock()->all(
                $this->strand->mock(),
                '<a>',
                '<b>'
            );

            $this->kernel->execute->calledWith('<a>');
            $this->kernel->execute->calledWith('<b>');

            Phony::inOrder(
                $call1 = $this->substrand1->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitAll::class)
                ),
                $call2 = $this->substrand2->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitAll::class)
                )
            );

            expect($call1->argument())->to->equal($call2->argument());
        });
    });

    describe('->any()', function () {
        it('attaches a StrandWaitAny instance to the substrands', function () {
            $this->subject->mock()->any(
                $this->strand->mock(),
                '<a>',
                '<b>'
            );

            $this->kernel->execute->calledWith('<a>');
            $this->kernel->execute->calledWith('<b>');

            Phony::inOrder(
                $call1 = $this->substrand1->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitAny::class)
                ),
                $call2 = $this->substrand2->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitAny::class)
                )
            );

            expect($call1->argument())->to->equal($call2->argument());
        });
    });

    describe('->some()', function () {
        it('attaches a StrandWaitSome instance to the substrands', function () {
            $this->subject->mock()->some(
                $this->strand->mock(),
                1,
                '<a>',
                '<b>'
            );

            $this->kernel->execute->calledWith('<a>');
            $this->kernel->execute->calledWith('<b>');

            Phony::inOrder(
                $call1 = $this->substrand1->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitSome::class)
                ),
                $call2 = $this->substrand2->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitSome::class)
                )
            );

            expect($call1->argument()->count())->to->equal(1);
            expect($call1->argument())->to->equal($call2->argument());

            $this->strand->throw->never()->called();
        });

        it('resumes the strand with an exception if the count is less than one', function () {
            $this->subject->mock()->some(
                $this->strand->mock(),
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
            $this->subject->mock()->some(
                $this->strand->mock(),
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
            $this->subject->mock()->first(
                $this->strand->mock(),
                '<a>',
                '<b>'
            );

            $this->kernel->execute->calledWith('<a>');
            $this->kernel->execute->calledWith('<b>');

            Phony::inOrder(
                $call1 = $this->substrand1->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitFirst::class)
                ),
                $call2 = $this->substrand2->setPrimaryListener->calledWith(
                    IsInstanceOf::anInstanceOf(StrandWaitFirst::class)
                )
            );

            expect($call1->argument())->to->equal($call2->argument());
        });
    });

});

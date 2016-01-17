<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use BadMethodCallException;
use Eloquent\Phony\Phpunit\Phony;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Recoil\Exception\RejectedException;
use Throwable;
use TypeError;
use UnexpectedValueException;

// @todo remove workaround
// @see https://github.com/eloquent/phony/issues/112
// $this->subject = Phony::partialMock([Api::class, ApiTrait::class]);
abstract class ApiTraitMock implements Api
{
    use ApiTrait;
}

class ApiTraitTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = Phony::mock(Kernel::class);
        $this->strand = Phony::mock(Strand::class);
        $this->strand->kernel->returns($this->kernel->mock());

        $this->substrand1 = Phony::mock(Strand::class);
        $this->substrand2 = Phony::mock(Strand::class);
        $this->kernel->execute->returns(
            $this->substrand1->mock(),
            $this->substrand2->mock()
        );

        $this->subject = Phony::partialMock(ApiTraitMock::class);
    }

    public function testDispatchWithNull()
    {
        $this->subject->mock()->__dispatch(
            $this->strand->mock(),
            0, // current generator key
            null
        );

        $this->subject->cooperate->calledWith(
            $this->strand->mock()
        );

        $this->strand->noInteraction();
    }

    public function testDispatchWithInteger()
    {
        $this->subject->mock()->__dispatch(
            $this->strand->mock(),
            0, // current generator key
            10
        );

        $this->subject->sleep->calledWith(
            $this->strand->mock(),
            10.0
        );

        $this->strand->noInteraction();
    }

    public function testDispatchWithFloat()
    {
        $this->subject->mock()->__dispatch(
            $this->strand->mock(),
            0, // current generator key
            10.5
        );

        $this->subject->sleep->calledWith(
            $this->strand->mock(),
            10.5
        );

        $this->strand->noInteraction();
    }

    public function testDispatchWithArray()
    {
        $this->subject->all->returns(null);

        $this->subject->mock()->__dispatch(
            $this->strand->mock(),
            0, // current generator key
            ['<a>', '<b>']
        );

        $this->subject->all->calledWith(
            $this->strand->mock(),
            '<a>',
            '<b>'
        );

        $this->strand->noInteraction();
    }

    public function testDispatchWithPromise()
    {
        $promise = Phony::mock(
            null,
            ['function then' => null]
        );

        $this->subject->mock()->__dispatch(
            $this->strand->mock(),
            0, // current generator key
            $promise->mock()
        );

        list($resolve, $reject) = $promise->then->calledWith(
            '~',
            '~'
        )->arguments()->all();

        $this->assertTrue(is_callable($resolve));
        $this->assertTrue(is_callable($reject));

        $this->strand->noInteraction();

        // Verify promise resolve is sent to strand ...
        $resolve('<result>');
        $this->strand->resume->calledWith('<result>');

        // Verify promise reject is sent to strand ...
        $exception = Phony::mock(Throwable::class)->mock();
        $reject($exception);
        $this->strand->throw->calledWith($exception);

        // Verify promise reject is sent to strand for non-exceptions...
        $reject('<reason>');
        $this->strand->throw->calledWith(new RejectedException('<reason>'));
    }

    public function testDispatchWithCancellablePromise()
    {
        $promise = Phony::mock(
            null,
            [
                'function then' => null,
                'function cancel' => null,
            ]
        );

        $this->subject->mock()->__dispatch(
            $this->strand->mock(),
            0, // current generator key
            $promise->mock()
        );

        $terminator = $this->strand->setTerminator->calledWith('~')->argument();

        $this->assertTrue(is_callable($terminator));

        $promise->cancel->never()->called();

        $terminator();

        $promise->cancel->called();
    }

    public function testDispatchFailure()
    {
        $this->subject->mock()->__dispatch(
            $this->strand->mock(),
            123, // current generator key
            '<string>'
        );

        $this->strand->throw->calledWith(
            new UnexpectedValueException(
                'The yielded pair (123, "<string>") does not describe any known operation.'
            )
        );
    }

    public function testCallMagicMethod()
    {
        $this->subject->mock()->unknown(
            $this->strand->mock()
        );

        $this->strand->throw->calledWith(
            new BadMethodCallException(
                'The API does not implement an operation named "unknown".'
            )
        );
    }

    public function testCallMagicMethodWithoutStrand()
    {
        $this->setExpectedException(
            TypeError::class,
            'must implement interface Recoil\Kernel\Strand'
        );

        $this->subject->mock()->unknown();
    }

    public function testSuspend()
    {
        $this->subject->mock()->suspend(
            $this->strand->mock()
        );

        $this->strand->noInteraction();
    }

    public function testSuspendWithCallback()
    {
        $fn = Phony::spy();

        $this->subject->mock()->suspend(
            $this->strand->mock(),
            $fn
        );

        $fn->calledWith($this->strand->mock());

        $this->strand->noInteraction();
    }

    public function testTerminate()
    {
        $this->subject->mock()->terminate(
            $this->strand->mock()
        );

        $this->strand->terminate->called();
    }

    public function testAll()
    {
        $this->subject->mock()->all(
            $this->strand->mock(),
            '<a>',
            '<b>'
        );

        $this->kernel->execute->calledWith('<a>');
        $this->kernel->execute->calledWith('<b>');

        Phony::inOrder(
            $call1 = $this->substrand1->attachObserver->calledWith(
                $this->isInstanceOf(StrandWaitAll::class)
            ),
            $call2 = $this->substrand2->attachObserver->calledWith(
                $this->isInstanceOf(StrandWaitAll::class)
            )
        );

        $this->assertSame(
            $call1->argument(),
            $call2->argument()
        );
    }

    public function testAny()
    {
        $this->subject->mock()->any(
            $this->strand->mock(),
            '<a>',
            '<b>'
        );

        $this->kernel->execute->calledWith('<a>');
        $this->kernel->execute->calledWith('<b>');

        Phony::inOrder(
            $call1 = $this->substrand1->attachObserver->calledWith(
                $this->isInstanceOf(StrandWaitAny::class)
            ),
            $call2 = $this->substrand2->attachObserver->calledWith(
                $this->isInstanceOf(StrandWaitAny::class)
            )
        );

        $this->assertSame(
            $call1->argument(),
            $call2->argument()
        );
    }

    public function testSome()
    {
        $this->subject->mock()->some(
            $this->strand->mock(),
            1,
            '<a>',
            '<b>'
        );

        $this->kernel->execute->calledWith('<a>');
        $this->kernel->execute->calledWith('<b>');

        Phony::inOrder(
            $call1 = $this->substrand1->attachObserver->calledWith(
                $this->isInstanceOf(StrandWaitSome::class)
            ),
            $call2 = $this->substrand2->attachObserver->calledWith(
                $this->isInstanceOf(StrandWaitSome::class)
            )
        );

        $this->assertEquals(
            1,
            $call1->argument()->count()
        );

        $this->assertSame(
            $call1->argument(),
            $call2->argument()
        );

        $this->strand->throw->never()->called();
    }

    public function testSomeWithCountTooLow()
    {
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
    }

    public function testSomeWithCountTooHigh()
    {
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
    }

    public function testFirst()
    {
        $this->subject->mock()->first(
            $this->strand->mock(),
            '<a>',
            '<b>'
        );

        $this->kernel->execute->calledWith('<a>');
        $this->kernel->execute->calledWith('<b>');

        Phony::inOrder(
            $call1 = $this->substrand1->attachObserver->calledWith(
                $this->isInstanceOf(StrandWaitFirst::class)
            ),
            $call2 = $this->substrand2->attachObserver->calledWith(
                $this->isInstanceOf(StrandWaitFirst::class)
            )
        );

        $this->assertSame(
            $call1->argument(),
            $call2->argument()
        );
    }
}

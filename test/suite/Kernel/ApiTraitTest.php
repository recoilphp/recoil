<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use BadMethodCallException;
use Eloquent\Phony\Phpunit\Phony;
use Exception;
use PHPUnit_Framework_TestCase;
use Recoil\Exception\RejectedException;
use UnexpectedValueException;

abstract class ApiTraitTestWorkaround implements Api
{
    use ApiTrait;
}

class ApiTraitTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->strand = Phony::mock(Strand::class);
        $this->caller = Phony::mock(Suspendable::class);

        // @todo remove workaround
        // @see https://github.com/eloquent/phony/issues/112
        $this->subject = Phony::partialMock(ApiTraitTestWorkaround::class);
        // $this->subject = Phony::partialMock([Api::class, ApiTrait::class]);
    }

    public function testDispatchWithAwaitableProvider()
    {
        $provider = Phony::mock(AwaitableProvider::class);
        $awaitable = Phony::mock(Awaitable::class);
        $provider->awaitable->returns($awaitable->mock());

        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            $provider->mock()
        );

        $awaitable->await->calledWith(
            $this->strand->mock(),
            $this->caller->mock(),
            $this->subject->mock()
        );

        $this->strand->noInteraction();
        $this->caller->noInteraction();
    }

    public function testDispatchWithAwaitable()
    {
        $awaitable = Phony::mock(Awaitable::class);

        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            $awaitable->mock()
        );

        $awaitable->await->calledWith(
            $this->strand->mock(),
            $this->caller->mock(),
            $this->subject->mock()
        );

        $this->strand->noInteraction();
        $this->caller->noInteraction();
    }

    public function testDispatchWithGenerator()
    {
        $awaitable = Phony::mock(Awaitable::class);

        $generator = function () use ($awaitable) {
            yield $awaitable->mock();
        };

        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            $generator()
        );

        $awaitable->await->calledWith(
            $this->strand->mock(),
            $this->isInstanceOf(Coroutine::class),
            $this->subject->mock()
        );

        $this->strand->noInteraction();
        $this->caller->noInteraction();
    }

    public function testDispatchWithCallable()
    {
        $fn = Phony::spy();

        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            $fn
        );

        $fn->called();

        // Verify that the result (null) does not get re-adapted into a call to
        // the "cooperate" API method.
        $this->subject->cooperate->never()->called();

        $this->caller->resume->calledWith(null);

        $this->strand->noInteraction();
    }

    public function testDispatchWithCallableThatReturnsAwaitableProvider()
    {
        $provider = Phony::mock(AwaitableProvider::class);
        $awaitable = Phony::mock(Awaitable::class);
        $provider->awaitable->returns($awaitable->mock());

        $fn = Phony::stub()->returns($provider->mock());

        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            $fn
        );

        $awaitable->await->calledWith(
            $this->strand->mock(),
            $this->caller->mock(),
            $this->subject->mock()
        );

        $this->strand->noInteraction();
        $this->caller->noInteraction();
    }

    public function testDispatchWithCallableThatReturnsAwaitable()
    {
        $awaitable = Phony::mock(Awaitable::class);

        $fn = Phony::stub()->returns($awaitable->mock());

        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            $fn
        );

        $awaitable->await->calledWith(
            $this->strand->mock(),
            $this->caller->mock(),
            $this->subject->mock()
        );

        $this->strand->noInteraction();
        $this->caller->noInteraction();
    }

    public function testDispatchWithCallableThatReturnsGenerator()
    {
        $awaitable = Phony::mock(Awaitable::class);

        $generator = function () use ($awaitable) {
            yield $awaitable->mock();
        };

        $fn = Phony::stub()->returns($generator());

        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            $fn
        );

        $awaitable->await->calledWith(
            $this->strand->mock(),
            $this->isInstanceOf(Coroutine::class),
            $this->subject->mock()
        );

        $this->strand->noInteraction();
        $this->caller->noInteraction();
    }

    public function testDispatchWithCallableThatReturnsValue()
    {
        $fn = Phony::stub()->returns('<value>');

        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            $fn
        );

        $this->caller->resume->calledWith('<value>');

        $this->strand->noInteraction();
    }

    public function testDispatchWithCallableThatThrows()
    {
        $exception = new Exception('Test exception!');

        $fn = Phony::stub()->throws($exception);

        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            $fn
        );

        $this->caller->throw->calledWith($exception);

        $this->strand->noInteraction();
    }

    public function testDispatchWithNull()
    {
        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            null
        );

        $this->subject->cooperate->calledWith(
            $this->strand->mock(),
            $this->caller->mock()
        );

        $this->strand->noInteraction();
        $this->caller->noInteraction();
    }

    public function testDispatchWithInteger()
    {
        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            10
        );

        $this->subject->sleep->calledWith(
            $this->strand->mock(),
            $this->caller->mock(),
            10.0
        );

        $this->strand->noInteraction();
        $this->caller->noInteraction();
    }

    public function testDispatchWithFloat()
    {
        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            10.5
        );

        $this->subject->sleep->calledWith(
            $this->strand->mock(),
            $this->caller->mock(),
            10.5
        );

        $this->strand->noInteraction();
        $this->caller->noInteraction();
    }

    public function testDispatchWithArray()
    {
        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            ['<a>', '<b>']
        );

        $this->subject->all->calledWith(
            $this->strand->mock(),
            $this->caller->mock(),
            '<a>',
            '<b>'
        );

        $this->strand->noInteraction();
        $this->caller->noInteraction();
    }

    public function testDispatchWithPromise()
    {
        $promise = Phony::mock(
            null,
            ['function then' => null]
        );

        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL, // irrelevant to this implementation
            $this->strand->mock(),
            $this->caller->mock(),
            $promise->mock()
        );

        $this->caller->noInteraction();

        list($resolve, $reject) = $promise->then->calledWith(
            '~',
            '~'
        )->arguments()->all();

        $this->assertTrue(is_callable($reject));
        $this->assertTrue(is_callable($reject));

        // Verify promise resolve is sent to caller ...
        $resolve('<result>');
        $this->caller->resume->calledWith('<result>');

        // Verify promise reject is sent to caller ...
        $exception = new Exception('<exception>');
        $reject($exception);
        $this->caller->throw->calledWith($exception);

        // Verify promise reject is sent to caller for non-exceptions...
        $reject('<reason>');
        $this->caller->throw->calledWith(new RejectedException('<reason>'));

        $this->strand->noInteraction();
    }

    public function testDispatchWithCancellablePromise()
    {
        $this->markTestIncomplete();

        $promise = Phony::mock(
            null,
            [
                'function then' => null,
                'function cancel' => null,
            ]
        );
    }

    public function testDispatchWithInvalidTask()
    {
        $this->subject->mock()->__dispatch(
            DispatchSource::KERNEL,
            $this->strand->mock(),
            $this->caller->mock(),
            '<string>'
        );

        $this->caller->throw->calledWith(
            new UnexpectedValueException(
                'The value ("<string>") does not describe any known operation.'
            )
        );

        $this->strand->noInteraction();
    }

    public function testDispatchWithInvalidTaskFromCoroutine()
    {
        $this->subject->mock()->__dispatch(
            DispatchSource::COROUTINE,
            $this->strand->mock(),
            $this->caller->mock(),
            '<string>',
            '<key>'
        );

        $this->caller->throw->calledWith(
            new UnexpectedValueException(
                'The yielded pair ("<key>", "<string>") does not describe any known operation.'
            )
        );

        $this->strand->noInteraction();
    }

    public function testCallMagicMethod()
    {
        $this->subject->mock()->unknown(
            $this->strand->mock(),
            $this->caller->mock()
        );

        $this->caller->throw->calledWith(
            new BadMethodCallException(
                'The API does not implement an operation named "unknown".'
            )
        );

        $this->strand->noInteraction();
    }

    public function testTerminate()
    {
        $this->subject->mock()->terminate(
            $this->strand->mock(),
            $this->caller->mock()
        );

        $this->strand->terminate->called();
        $this->caller->noInteraction();
    }

    public function testAll()
    {
        $this->markTestIncomplete();
    }

    public function testAny()
    {
        $this->markTestIncomplete();
    }

    public function testSome()
    {
        $this->markTestIncomplete();
    }

    public function testRace()
    {
        $this->markTestIncomplete();
    }
}

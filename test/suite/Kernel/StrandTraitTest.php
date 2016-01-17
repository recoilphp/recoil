<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Eloquent\Phony\Phpunit\Phony;
use Exception;
use Generator;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use Throwable;

// @todo remove workaround
// @see https://github.com/eloquent/phony/issues/112
// $this->subject = Phony::partialMock([Strand::class, StrandTrait::class]);
abstract class StrandTraitMock implements Strand
{
    use StrandTrait;
}

class StrandTraitTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = Phony::mock(Kernel::class);
        $this->api = Phony::mock(Api::class);

        $this->subject = Phony::partialMock(
            StrandTraitMock::class,
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
    }

    public function tearDown()
    {
        $this->observer2->noInteraction();
    }

    public function testId()
    {
        $this->assertSame(
            123,
            $this->subject->mock()->id()
        );
    }

    public function testKernel()
    {
        $this->assertSame(
            $this->kernel->mock(),
            $this->subject->mock()->kernel()
        );
    }

    public function testStartWithGeneratorObject()
    {
        $fn = Phony::spy(function () {
            yield '<key>' => '<value>';
        });

        $this->subject->mock()->start($fn());

        $this->api->__dispatch->calledWith(
            $this->subject->mock(),
            '<key>',
            '<value>'
        );

        $fn->never()->received();
        $fn->never()->receivedException();
    }

    public function testStartWithGeneratorFunction()
    {
        $fn = Phony::spy(function () {
            yield '<key>' => '<value>';
        });

        $this->subject->mock()->start($fn);

        $this->api->__dispatch->calledWith(
            $this->subject->mock(),
            '<key>',
            '<value>'
        );

        $fn->never()->received();
        $fn->never()->receivedException();
    }

    public function testStartWithCoroutineProvider()
    {
        $provider = Phony::mock(CoroutineProvider::class);
        $provider->coroutine->does(
            function () { yield '<key>' => '<value>'; }
        );

        $this->subject->mock()->start($provider->mock());

        $this->api->__dispatch->calledWith(
            $this->subject->mock(),
            '<key>',
            '<value>'
        );
    }

    public function testStartWithOtherFunction()
    {
        $this->setExpectedException(
            InvalidArgumentException::class,
            'Callable must return a generator.'
        );

        $this->subject->mock()->start(function () {});
    }

    public function testStartWithOtherType()
    {
        $this->subject->mock()->start('<value>');

        $this->api->__dispatch->calledWith(
            $this->subject->mock(),
            0,
            '<value>'
        );
    }

    public function testStartWhenTerminated()
    {
        $this->subject->mock()->terminate();

        $this->subject->mock()->start('<value>');

        $this->api->noInteraction();
    }

    public function testTerminate()
    {
        $fn = Phony::spy();

        $this->subject->mock()->setTerminator($fn);

        $this->subject->mock()->terminate();
        $this->subject->mock()->terminate();

        $fn->once()->calledWith($this->subject->mock());

        $this->observer1->terminated->once()->calledWith(
            $this->subject->mock()
        );
    }

    public function testResume()
    {
        $fn = Phony::spy(function () {
            yield;
        });

        $this->subject->mock()->start($fn);
        $this->subject->mock()->resume('<result>');

        $fn->received('<result>');
    }

    public function testResumeWhenTicking()
    {
        $fn = Phony::spy(function () {
            yield;
        });

        $this->api->__dispatch->does(
            function () {
                $this->subject->mock()->resume('<result>');
            }
        );

        $this->subject->mock()->start($fn);

        $fn->received('<result>');
    }

    public function testResumeWhenTerminated()
    {
        $fn = Phony::spy(function () {
            yield;
        });

        $this->subject->mock()->start($fn);
        $this->subject->mock()->terminate();
        $this->subject->mock()->resume('<result>');

        $fn->never()->received();
        $fn->never()->receivedException();
    }

    public function testThrow()
    {
        $fn = Phony::spy(function () {
            yield;
        });

        $this->subject->mock()->start($fn);

        $exception = Phony::mock(Throwable::class)->mock();
        $this->subject->mock()->throw($exception);

        $fn->receivedException($exception);
    }

    public function testThrowWhenTicking()
    {
        $fn = Phony::spy(function () {
            yield;
        });

        $exception = Phony::mock(Throwable::class)->mock();
        $this->api->__dispatch->does(
            function () use ($exception) {
                $this->subject->mock()->throw($exception);
            }
        );

        $this->subject->mock()->start($fn);

        $fn->receivedException($exception);
    }

    public function testThrowWhenTerminated()
    {
        $fn = Phony::spy(function () {
            yield;
        });

        $this->subject->mock()->start($fn);
        $this->subject->mock()->terminate();

        $exception = Phony::mock(Throwable::class)->mock();
        $this->subject->mock()->throw($exception);

        $fn->never()->received();
        $fn->never()->receivedException();
    }

    public function testAwaitable()
    {
        $awaitable = $this->subject->mock()->awaitable();

        $this->assertEquals(
            new StrandWaitOne($this->subject->mock()),
            $awaitable
        );
    }

    public function testEntryPointReturn()
    {
        $fn = function () {
            return '<result>';
            yield;
        };

        $this->subject->mock()->start($fn);

        $this->observer1->success->calledWith(
            $this->subject->mock(),
            '<result>'
        );
    }

    public function testEntryPointThrow()
    {
        $exception = Phony::mock(Throwable::class)->mock();

        $fn = function () use ($exception) {
            throw $exception;
            yield;
        };

        $this->subject->mock()->start($fn);

        $this->observer1->failure->calledWith(
            $this->subject->mock(),
            $exception
        );
    }

    public function testEntryPointThrowWithNoObservers()
    {
        $this->subject->mock()->detachObserver($this->observer1->mock());

        $exception = new Exception('<exception>');

        $fn = function () use ($exception) {
            throw $exception;
            yield;
        };

        $this->setExpectedException(
            Exception::class,
            '<exception>'
        );

        $this->subject->mock()->start($fn);
    }

    public function testCallStackReturn()
    {
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
            $this->subject->mock(),
            '<ok>'
        );
    }

    public function testCallStackThrow()
    {
        $exception = Phony::mock(Throwable::class)->mock();

        $fn2 = function () use ($exception) {
            throw $exception;
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
            $this->subject->mock(),
            '<ok>'
        );
    }

    public function testCallCoroutineProvider()
    {
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

        $this->observer1->success->calledWith(
            $this->subject->mock(),
            '<result>'
        );
    }

    public function testCallApi()
    {
        $fn = Phony::spy(function () {
            yield new ApiCall('<name>', [1, 2, 3]);
        });

        $this->subject->mock()->start($fn);

        $this->api->{'<name>'}->calledWith(
            $this->subject->mock(),
            1,
            2,
            3
        );

        $fn->never()->received();
        $fn->never()->receivedException();
    }

    public function testCallAwaitable()
    {
        $awaitable = Phony::mock(Awaitable::class);

        $fn = Phony::spy(function () use ($awaitable) {
            yield $awaitable->mock();
        });

        $this->subject->mock()->start($fn);

        $awaitable->await->calledWith(
            $this->subject->mock(),
            $this->api->mock()
        );

        $fn->never()->received();
        $fn->never()->receivedException();
    }

    public function testCallAwaitableProvider()
    {
        $provider = Phony::mock(AwaitableProvider::class);
        $awaitable = Phony::mock(Awaitable::class);
        $provider->awaitable->returns($awaitable->mock());

        $fn = Phony::spy(function () use ($provider) {
            yield $provider->mock();
        });

        $this->subject->mock()->start($fn);

        $awaitable->await->calledWith(
            $this->subject->mock(),
            $this->api->mock()
        );

        $fn->never()->received();
        $fn->never()->receivedException();
    }

    public function testCallFailure()
    {
        $exception = Phony::mock(Throwable::class)->mock();
        $this->api->__dispatch->throws($exception);

        $fn = Phony::spy(function () {
            yield;
        });

        $this->subject->mock()->start($fn);

        $fn->receivedException($exception);

        $this->observer1->failure->calledWith(
            $this->subject->mock(),
            $exception
        );
    }
}

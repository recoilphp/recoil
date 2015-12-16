<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Eloquent\Phony\Phpunit\Phony;
use Exception;
use PHPUnit_Framework_TestCase;

class CoroutineTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->strand = Phony::mock(Strand::class);
        $this->caller = Phony::mock(Suspendable::class);
        $this->api = Phony::mock(Api::class);

        $this->useGenerator(
            function () {
                yield '<key>' => '<value>';

                return '<result>';
            }
        );
    }

    public function tearDown()
    {
        $this->strand->noInteraction();
    }

    public function useGenerator(callable $fn)
    {
        $spy = Phony::spy($fn);
        $this->spy = $spy;
        $this->generator = $spy();
        $this->subject = new Coroutine($this->generator);
    }

    public function testAwait()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->caller->mock(),
            $this->api->mock()
        );

        $this->api->__dispatch->calledWith(
            DispatchSource::COROUTINE,
            $this->strand->mock(),
            $this->subject,
            '<value>',
            '<key>'
        );

        $this->spy->never()->received();
        $this->spy->never()->receivedException();
    }

    public function testResume()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->caller->mock(),
            $this->api->mock()
        );

        $this->caller->noInteraction();

        $this->subject->resume('<result>');

        $this->spy->received('<result>');

        $this->caller->resume->calledWith('<result>');
    }

    public function testResumeDuringTick()
    {
        $this->api->__dispatch->does(
            function ($source, $strand, $coroutine) {
                $coroutine->resume('<result>');
            }
        );

        $this->subject->await(
            $this->strand->mock(),
            $this->caller->mock(),
            $this->api->mock()
        );

        $this->spy->received('<result>');

        $this->caller->resume->calledWith('<result>');
    }

    public function testThrow()
    {
        $exception = new Exception('<exception>');

        $this->subject->await(
            $this->strand->mock(),
            $this->caller->mock(),
            $this->api->mock()
        );

        $this->caller->noInteraction();

        $this->subject->throw($exception);

        $this->spy->receivedException($exception);

        $this->caller->throw->calledWith($exception);
    }

    public function testThrowDuringTick()
    {
        $exception = new Exception('<exception>');

        $this->api->__dispatch->does(
            function ($source, $strand, $coroutine) use ($exception) {
                $coroutine->throw($exception);
            }
        );

        $this->subject->await(
            $this->strand->mock(),
            $this->caller->mock(),
            $this->api->mock()
        );

        $this->spy->receivedException($exception);

        $this->caller->throw->calledWith($exception);
    }

    public function testExceptionInApiResumesCoroutineNotCaller()
    {
        $this->markTestIncomplete();
    }
}

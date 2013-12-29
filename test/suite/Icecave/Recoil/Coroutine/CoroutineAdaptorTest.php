<?php
namespace Icecave\Recoil\Coroutine;

use Icecave\Recoil\Kernel\Api\KernelApiCall;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Kernel\Strand\StrandInterface;
use Icecave\Recoil\Recoil;
use Phake;
use PHPUnit_Framework_TestCase;
use React\Promise\PromiseInterface;

class CoroutineAdaptorTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->strand  = Phake::mock(StrandInterface::CLASS);
        $this->adaptor = new CoroutineAdaptor;
    }

    public function testAdaptPassThru()
    {
        $coroutine = Phake::mock(CoroutineInterface::CLASS);

        $this->assertSame(
            $coroutine,
            $this->adaptor->adapt($this->strand, $coroutine)
        );
    }

    public function testAdaptWithGenerator()
    {
        $generator = call_user_func(
            function () {
                echo '123'; yield;
            }
        );

        $coroutine = $this->adaptor->adapt($this->strand, $generator);

        $this->assertInstanceOf(GeneratorCoroutine::CLASS, $coroutine);

        $this->expectOutputString('123');
        $coroutine->call($this->strand);
    }

    public function testAdaptWithPromise()
    {
        $promise = Phake::mock(PromiseInterface::CLASS);
        $coroutine = $this->adaptor->adapt($this->strand, $promise);

        $this->assertInstanceOf(PromiseCoroutine::CLASS, $coroutine);
        $this->assertSame($promise, $coroutine->promise());
    }

    public function testAdaptWithNull()
    {
        $coroutine = $this->adaptor->adapt($this->strand, null);

        $this->assertInstanceOf(KernelApiCall::CLASS, $coroutine);
        $this->assertSame('cooperate', $coroutine->name());
    }

    public function testAdaptFailure()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Unable to adapt 123 into a coroutine.'
        );

        $this->adaptor->adapt($this->strand, 123);
    }

    public function testAdaptProvider()
    {
        $provider = Phake::mock(CoroutineProviderInterface::CLASS);
        $coroutine = Phake::mock(CoroutineInterface::CLASS);

        Phake::when($provider)
            ->coroutine($this->identicalTo($this->strand))
            ->thenReturn($coroutine);

        $this->assertSame(
            $coroutine,
            $this->adaptor->adapt($this->strand, $provider)
        );
    }

    public function testAdaptNestedProvider()
    {
        $provider = Phake::mock(CoroutineProviderInterface::CLASS);
        $coroutine = Phake::mock(CoroutineInterface::CLASS);

        Phake::when($provider)
            ->coroutine($this->identicalTo($this->strand))
            ->thenReturn($provider)
            ->thenReturn($coroutine);

        $this->assertSame(
            $coroutine,
            $this->adaptor->adapt($this->strand, $provider)
        );
    }
}

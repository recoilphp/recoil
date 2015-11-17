<?php

namespace Recoil\Coroutine;

use Phake;
use PHPUnit_Framework_TestCase;
use React\Promise\PromiseInterface;
use Recoil\Kernel\Api\KernelApiCall;
use Recoil\Kernel\Strand\StrandInterface;

class CoroutineAdaptorTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->strand  = Phake::mock(StrandInterface::class);
        $this->adaptor = new CoroutineAdaptor();
    }

    public function testAdaptPassThru()
    {
        $coroutine = Phake::mock(CoroutineInterface::class);

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

        $this->assertInstanceOf(GeneratorCoroutine::class, $coroutine);

        $this->expectOutputString('123');
        $coroutine->call($this->strand);
    }

    public function testAdaptWithPromise()
    {
        $promise   = Phake::mock(PromiseInterface::class);
        $coroutine = $this->adaptor->adapt($this->strand, $promise);

        $this->assertInstanceOf(PromiseCoroutine::class, $coroutine);
    }

    public function testAdaptWithArray()
    {
        $coroutine = $this->adaptor->adapt($this->strand, ['a', 'b', 'c']);

        $this->assertInstanceOf(KernelApiCall::class, $coroutine);
        $this->assertSame('all', $coroutine->name());
        $this->assertSame([['a', 'b', 'c']], $coroutine->arguments());
    }

    public function testAdaptWithNull()
    {
        $coroutine = $this->adaptor->adapt($this->strand, null);

        $this->assertInstanceOf(KernelApiCall::class, $coroutine);
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
        $provider  = Phake::mock(CoroutineProviderInterface::class);
        $coroutine = Phake::mock(CoroutineInterface::class);

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
        $provider  = Phake::mock(CoroutineProviderInterface::class);
        $coroutine = Phake::mock(CoroutineInterface::class);

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

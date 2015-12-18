<?php

declare (strict_types = 1);

// namespace Recoil\React;

// use Eloquent\Phony\Phpunit\Phony;
// use PHPUnit_Framework_TestCase;
// use React\EventLoop\LoopInterface;
// use Recoil\Kernel\Awaitable;
// use Recoil\Kernel\Strand;
// use Recoil\Kernel\Suspendable;

// class ReactApiTest extends PHPUnit_Framework_TestCase
// {
//     public function setUp()
//     {
//         $this->eventLoop = Phony::mock(LoopInterface::class);
//         $this->strand = Phony::mock(Strand::class);
//         $this->caller = Phony::mock(Suspendable::class);

//         $this->subject = new ReactApi($this->eventLoop->mock());
//     }

//     public function tearDown()
//     {
//         $this->strand->noInteraction();
//     }

//     public function testExecute()
//     {
//         $awaitable = Phony::mock(Awaitable::class);

//         $this->subject->execute(
//             $this->strand->mock(),
//             $this->caller->mock(),
//             $awaitable->mock()
//         );

//         Phony::inOrder(
//             $this->eventLoop->futureTick->calledWith('~'),
//             $this->caller->resume->calledWith(
//                 $this->isInstanceOf(ReactStrand::class)
//             )
//         );

//         $strand = $this->caller->resume->argument();
//         $fn = $this->eventLoop->futureTick->argument();

//         $this->assertTrue(is_callable($fn));

//         $awaitable->noInteraction();

//         $fn();

//         $awaitable->await->calledWith(
//             $strand,
//             $strand,
//             $this->subject
//         );
//     }

//     public function testCallback()
//     {
//         $awaitable = Phony::mock(Awaitable::class);

//         $this->subject->callback(
//             $this->strand->mock(),
//             $this->caller->mock(),
//             $awaitable->mock()
//         );

//         $fn = $this->caller->resume->calledWith('~')->argument();

//         $this->assertTrue(is_callable($fn));

//         $fn();

//         $awaitable->await->calledWith(
//             $this->isInstanceOf(ReactStrand::class),
//             $this->isInstanceOf(ReactStrand::class),
//             $this->subject
//         );

//         $this->assertSame(
//             $awaitable->await->argument(0),
//             $awaitable->await->argument(1)
//         );
//     }

//     public function testCooperate()
//     {
//         $this->subject->cooperate(
//             $this->strand->mock(),
//             $this->caller->mock()
//         );

//         $fn = $this->eventLoop->futureTick->calledWith('~')->argument();
//         $this->assertTrue(is_callable($fn));

//         $this->caller->noInteraction();

//         $fn();

//         $this->caller->resume->calledWith();
//     }

//     public function testSleep()
//     {
//         $this->subject->sleep(
//             $this->strand->mock(),
//             $this->caller->mock(),
//             10.5
//         );

//         $fn = $this->eventLoop->addTimer->calledWith(10.5, '~')->argument(1);
//         $this->assertTrue(is_callable($fn));

//         $this->caller->noInteraction();

//         $fn();

//         $this->caller->resume->calledWith();
//     }

//     public function testSleepWithZeroSeconds()
//     {
//         $this->subject->sleep(
//             $this->strand->mock(),
//             $this->caller->mock(),
//             0
//         );

//         $fn = $this->eventLoop->futureTick->calledWith('~')->argument();
//         $this->assertTrue(is_callable($fn));

//         $this->caller->noInteraction();

//         $fn();

//         $this->caller->resume->calledWith();
//     }

//     public function testSleepWithZeroLessThenZeroSeconds()
//     {
//         $this->subject->sleep(
//             $this->strand->mock(),
//             $this->caller->mock(),
//             -1
//         );

//         $fn = $this->eventLoop->futureTick->calledWith('~')->argument();
//         $this->assertTrue(is_callable($fn));

//         $this->caller->noInteraction();

//         $fn();

//         $this->caller->resume->calledWith();
//     }

//     public function testTimeout()
//     {
//         $this->markTestIncomplete();
//     }

//     public function testEventLoop()
//     {
//         $this->subject->eventLoop(
//             $this->strand->mock(),
//             $this->caller->mock()
//         );

//         $this->caller->resume->calledWith($this->eventLoop->mock());
//     }
// }


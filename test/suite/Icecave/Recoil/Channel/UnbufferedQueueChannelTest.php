<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;

class UnbufferedQueueChannelTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel  = new Kernel;
        $this->channel = new UnbufferedQueueChannel;
    }

    public function testReadThenWrite()
    {
        $this->expectOutputString(
            'Reading' . PHP_EOL .
            'Writing' . PHP_EOL .
            'Write complete' . PHP_EOL .
            'Read foo' . PHP_EOL
        );

        $this->kernel->execute(
            call_user_func(
                function () {
                    echo 'Reading' . PHP_EOL;
                    $value = (yield $this->channel->read());
                    echo 'Read ' . $value . PHP_EOL;
                }
            )
        );

        $this->kernel->execute(
            call_user_func(
                function () {
                    echo 'Writing' . PHP_EOL;
                    yield $this->channel->write('foo');
                    echo 'Write complete' . PHP_EOL;
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testWriteThenRead()
    {
        $this->expectOutputString(
            'Writing' . PHP_EOL .
            'Reading' . PHP_EOL .
            'Read foo' . PHP_EOL .
            'Write complete' . PHP_EOL
        );

        $this->kernel->execute(
            call_user_func(
                function () {
                    echo 'Writing' . PHP_EOL;
                    yield $this->channel->write('foo');
                    echo 'Write complete' . PHP_EOL;
                }
            )
        );

        $this->kernel->execute(
            call_user_func(
                function () {
                    echo 'Reading' . PHP_EOL;
                    $value = (yield $this->channel->read());
                    echo 'Read ' . $value . PHP_EOL;
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testMultipleReaders()
    {
        $this->expectOutputString('A1B2A3B4A5');

        $reader = function ($id) {
            while (true) {
                echo $id . (yield $this->channel->read());
            }
        };

        $this->kernel->execute($reader('A'));
        $this->kernel->execute($reader('B'));

        $this->kernel->execute(
            call_user_func(
                function () {
                    for ($i = 1; $i <= 5; ++$i) {
                        yield $this->channel->write($i);
                    }
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testMultipleWriters()
    {
        $this->expectOutputString('A1B2A3B4A5');

        $next = 1;
        $writer = function ($id) use (&$next) {
            while (true) {
                yield $this->channel->write($id . $next++);
            }
        };

        $this->kernel->execute($writer('A'));
        $this->kernel->execute($writer('B'));

        $this->kernel->execute(
            call_user_func(
                function () {
                    for ($i = 1; $i <= 5; ++$i) {
                        echo (yield $this->channel->read());
                    }
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testCloseWithPendingReaders()
    {
        $this->expectOutputString(
            'A reading' . PHP_EOL .
            'B reading' . PHP_EOL .
            'closing' . PHP_EOL .
            'B closed' . PHP_EOL .
            'A closed' . PHP_EOL
        );

        $reader = function ($id) {
            try {
                echo $id . ' reading' . PHP_EOL;
                yield $this->channel->read();
            } catch (ChannelClosedException $e) {
                echo $id . ' closed' . PHP_EOL;
            }
        };

        $this->kernel->execute($reader('A'));
        $this->kernel->execute($reader('B'));
        $this->kernel->execute(
            call_user_func(
                function () {
                    yield;
                    echo 'closing' . PHP_EOL;
                    yield $this->channel->close();
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testCloseWithPendingWriters()
    {
        $this->expectOutputString(
            'A writing' . PHP_EOL .
            'B writing' . PHP_EOL .
            'closing' . PHP_EOL .
            'B closed' . PHP_EOL .
            'A closed' . PHP_EOL
        );

        $writer = function ($id) {
            try {
                echo $id . ' writing' . PHP_EOL;
                yield $this->channel->write($id);
            } catch (ChannelClosedException $e) {
                echo $id . ' closed' . PHP_EOL;
            }
        };

        $this->kernel->execute($writer('A'));
        $this->kernel->execute($writer('B'));
        $this->kernel->execute(
            call_user_func(
                function () {
                    yield;
                    echo 'closing' . PHP_EOL;
                    yield $this->channel->close();
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testReadWhenClosed()
    {
        $this->kernel->execute(
            call_user_func(
                function () {
                    yield $this->channel->close();
                    $this->setExpectedException(ChannelClosedException::CLASS);
                    yield $this->channel->read();
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testWriteWhenClosed()
    {
        $this->kernel->execute(
            call_user_func(
                function () {
                    yield $this->channel->close();
                    $this->setExpectedException(ChannelClosedException::CLASS);
                    yield $this->channel->write('foo');
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testReadyToRead()
    {
        $this->kernel->execute(
            call_user_func(
                function () {
                    $this->assertFalse($this->channel->readyToRead());
                    yield $this->channel->write('foo');
                    $this->assertTrue($this->channel->readyToRead());
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testWriteWillBlock()
    {
        $this->kernel->execute(
            call_user_func(
                function () {
                    $this->assertFalse($this->channel->readyForWrite());
                    yield $this->channel->read();
                    $this->assertTrue($this->channel->readyForWrite());
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }
}

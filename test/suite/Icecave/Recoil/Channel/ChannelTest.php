<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;

class ChannelTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel  = new Kernel;
        $this->channel = new Channel;
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
        $this->expectOutputString(
            '0: 1' . PHP_EOL .
            '1: 2' . PHP_EOL .
            '0: 3' . PHP_EOL .
            '1: 4' . PHP_EOL .
            '0: 5' . PHP_EOL .
            '1: 6' . PHP_EOL .
            '0: 7' . PHP_EOL .
            '1: 8' . PHP_EOL .
            '0: 9' . PHP_EOL .
            '1: 10' . PHP_EOL .
            '0: 11' . PHP_EOL .
            '1: 12' . PHP_EOL .
            '0: 13' . PHP_EOL .
            '1: 14' . PHP_EOL .
            '0: 15' . PHP_EOL .
            '0 closed' . PHP_EOL .
            '1 closed' . PHP_EOL
        );

        $reader = function ($id) {
            try {
                while (true) {
                    echo $id . ': ' . (yield $this->channel->read()) . PHP_EOL;
                }
            } catch (Exception $e) {
                echo $id . ' closed' . PHP_EOL;
            }
        };

        $this->kernel->execute($reader(0));
        $this->kernel->execute($reader(1));

        $this->kernel->execute(
            call_user_func(
                function () {
                    for ($i = 1; $i <= 15; ++$i) {
                        yield $this->channel->write($i);
                    }

                    yield $this->channel->close();
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testMultipleWriters()
    {
        $this->expectOutputString(
            '0: 1' . PHP_EOL .
            '1: 1' . PHP_EOL .
            '0: 2' . PHP_EOL .
            '1: 2' . PHP_EOL .
            '0: 3' . PHP_EOL .
            '1: 3' . PHP_EOL .
            '0: 4' . PHP_EOL .
            '1: 4' . PHP_EOL .
            '0: 5' . PHP_EOL .
            '1: 5' . PHP_EOL .
            '0: 6' . PHP_EOL .
            '1: 6' . PHP_EOL .
            '0: 7' . PHP_EOL .
            '1: 7' . PHP_EOL .
            '0: 8' . PHP_EOL .
            '0 closed' . PHP_EOL .
            '1 closed' . PHP_EOL
        );

        $writer = function ($id) {
            try {
                $i = 1;
                while (true) {
                    yield $this->channel->write($id . ': ' . $i++);
                }
            } catch (Exception $e) {
                echo $id . ' closed' . PHP_EOL;
            }
        };

        $this->kernel->execute($writer(0));
        $this->kernel->execute($writer(1));

        $this->kernel->execute(
            call_user_func(
                function () {
                    for ($i = 1; $i <= 15; ++$i) {
                        echo (yield $this->channel->read()) . PHP_EOL;
                    }

                    yield $this->channel->close();
                }
            )
        );

        $this->kernel->eventLoop()->run();
    }

    public function testCloseWithPendingReaders()
    {
        $this->expectOutputString(
            'Reader #1 closed' . PHP_EOL .
            'Reader #2 closed' . PHP_EOL
        );

        $reader = function ($id) {
            try {
                yield $this->channel->read();
            } catch (ChannelClosedException $e) {
                echo 'Reader #' . $id . ' closed' . PHP_EOL;
            }
        };

        $this->kernel->execute($reader(1));
        $this->kernel->execute($reader(2));
        $this->kernel->execute($this->channel->close());

        $this->kernel->eventLoop()->run();
    }

    public function testCloseWithPendingWriters()
    {
        $this->expectOutputString(
            'Writer #1 closed' . PHP_EOL .
            'Writer #2 closed' . PHP_EOL
        );

        $writer = function ($id) {
            try {
                yield $this->channel->write($id);
            } catch (ChannelClosedException $e) {
                echo 'Writer #' . $id . ' closed' . PHP_EOL;
            }
        };

        $this->kernel->execute($writer(1));
        $this->kernel->execute($writer(2));
        $this->kernel->execute($this->channel->close());

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

<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
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

        $reader = function () {
            echo 'Reading' . PHP_EOL;
            $value = (yield $this->channel->read());
            echo 'Read ' . $value . PHP_EOL;
        };

        $writer = function () {
            echo 'Writing' . PHP_EOL;
            yield $this->channel->write('foo');
            echo 'Write complete' . PHP_EOL;
        };

        $this->kernel->execute($reader());
        $this->kernel->execute($writer());

        $this->kernel->eventLoop()->run();
    }

    public function testWriteThenRead()
    {
        $this->expectOutputString(
            'Writing' . PHP_EOL .
            'Reading' . PHP_EOL .
            'Write complete' . PHP_EOL .
            'Read foo' . PHP_EOL
        );

        $reader = function () {
            echo 'Reading' . PHP_EOL;
            $value = (yield $this->channel->read());
            echo 'Read ' . $value . PHP_EOL;
        };

        $writer = function () {
            echo 'Writing' . PHP_EOL;
            yield $this->channel->write('foo');
            echo 'Write complete' . PHP_EOL;
        };

        $this->kernel->execute($writer());
        $this->kernel->execute($reader());

        $this->kernel->eventLoop()->run();
    }

    public function testCloseWithPendingReader()
    {
        $this->expectOutputString(
            'Reading' . PHP_EOL .
            'Closing' . PHP_EOL .
            'Closed' . PHP_EOL
        );

        $reader = function () {
            try {
                echo 'Reading' . PHP_EOL;
                yield $this->channel->read();
            } catch (ChannelClosedException $e) {
                echo 'Closed' . PHP_EOL;
            }
        };

        $closer = function () {
            echo 'Closing' . PHP_EOL;
            yield $this->channel->close();
        };

        $this->kernel->execute($reader());
        $this->kernel->execute($closer());

        $this->kernel->eventLoop()->run();
    }

    public function testCloseWithPendingWriter()
    {
        $this->expectOutputString(
            'Writing' . PHP_EOL .
            'Closing' . PHP_EOL .
            'Closed' . PHP_EOL
        );

        $writer = function () {
            try {
                echo 'Writing' . PHP_EOL;
                yield $this->channel->write(null);
            } catch (ChannelClosedException $e) {
                echo 'Closed' . PHP_EOL;
            }
        };

        $closer = function () {
            echo 'Closing' . PHP_EOL;
            yield $this->channel->close();
        };

        $this->kernel->execute($writer());
        $this->kernel->execute($closer());

        $this->kernel->eventLoop()->run();
    }

    public function testReadWhenClosed()
    {
        $reader = function () {
            yield $this->channel->close();
            $this->setExpectedException(ChannelClosedException::CLASS);
            yield $this->channel->read();
        };

        $this->kernel->execute($reader());

        $this->kernel->eventLoop()->run();
    }

    public function testWriteWhenClosed()
    {
        $writer = function () {
            yield $this->channel->close();
            $this->setExpectedException(ChannelClosedException::CLASS);
            yield $this->channel->write(null);
        };

        $this->kernel->execute($writer());

        $this->kernel->eventLoop()->run();
    }

    public function testReadWhenLocked()
    {
        $reader = function () {
            $this->setExpectedException(ChannelLockedException::CLASS);
            yield $this->channel->read();
        };

        $this->kernel->execute($this->channel->read());
        $this->kernel->execute($reader());

        $this->kernel->eventLoop()->run();
    }

    public function testWriteWhenLocked()
    {
        $writer = function () {
            $this->setExpectedException(ChannelLockedException::CLASS);
            yield $this->channel->write(null);
        };

        $this->kernel->execute($this->channel->write(null));
        $this->kernel->execute($writer());

        $this->kernel->eventLoop()->run();
    }
}

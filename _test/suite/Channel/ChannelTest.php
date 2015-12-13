<?php

namespace Recoil\Channel;

use PHPUnit_Framework_TestCase;
use Recoil\Channel\Exception\ChannelClosedException;
use Recoil\Kernel\StandardKernel;

class ChannelTest extends PHPUnit_Framework_TestCase
{
    use ChannelTestTrait;
    use LoopbackChannelTestTrait;
    use ReadableChannelTestTrait;
    use WritableChannelTestTrait;

    public function setUp()
    {
        $this->kernel  = new StandardKernel();
        $this->channel = new Channel();
    }

    public function testMultipleReaders()
    {
        $this->expectOutputString('A1B2A3B4A5');

        $reader = function ($id) {
            while (true) {
                echo $id . (yield $this->channel->read());
            }
        };

        $writer = function () {
            for ($i = 1; $i <= 5; ++$i) {
                yield $this->channel->write($i);
            }
        };

        $this->kernel->execute($reader('A'));
        $this->kernel->execute($reader('B'));
        $this->kernel->execute($writer());
        $this->kernel->eventLoop()->run();
    }

    public function testMultipleWriters()
    {
        $this->expectOutputString('A1B2A3B4A5');

        $reader = function () {
            for ($i = 1; $i <= 5; ++$i) {
                echo(yield $this->channel->read());
            }
        };

        $next   = 1;
        $writer = function ($id) use (&$next) {
            while (true) {
                yield $this->channel->write($id . $next++);
            }
        };

        $this->kernel->execute($writer('A'));
        $this->kernel->execute($writer('B'));
        $this->kernel->execute($reader());
        $this->kernel->eventLoop()->run();
    }

    public function testCloseWithMultiplePendingReaders()
    {
        $output = [];

        $reader = function ($id) use (&$output) {
            try {
                $output[] = $id . ' reading';
                yield $this->channel->read();
            } catch (ChannelClosedException $e) {
                $output[] = $id . ' closed';
            }
        };

        $closer = function () use (&$output) {
            $output[] = 'closing';
            yield $this->channel->close();
        };

        $this->kernel->execute($reader('A'));
        $this->kernel->execute($reader('B'));
        $this->kernel->execute($closer());
        $this->kernel->eventLoop()->run();

        $this->assertContains(
            $output,
            [
                ['A reading', 'B reading', 'closing', 'A closed', 'B closed'],
                ['A reading', 'B reading', 'closing', 'B closed', 'A closed'],
            ]
        );
    }

    public function testCloseWithMultiplePendingWriters()
    {
        $output = [];

        $writer = function ($id) use (&$output) {
            try {
                $output[] = $id . ' writing';
                yield $this->channel->write($id);
            } catch (ChannelClosedException $e) {
                $output[] = $id . ' closed';
            }
        };

        $closer = function () use (&$output) {
            $output[] = 'closing';
            yield $this->channel->close();
        };

        $this->kernel->execute($writer('A'));
        $this->kernel->execute($writer('B'));
        $this->kernel->execute($closer());
        $this->kernel->eventLoop()->run();

        $this->assertContains(
            $output,
            [
                ['A writing', 'B writing', 'closing', 'A closed', 'B closed'],
                ['A writing', 'B writing', 'closing', 'B closed', 'A closed'],
            ]
        );
    }
}

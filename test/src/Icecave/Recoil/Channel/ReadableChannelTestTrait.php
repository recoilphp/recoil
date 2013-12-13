<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;

trait ReadableChannelTestTrait
{
    public function testCloseWithPendingReader()
    {
        $output = [];

        $reader = function () use (&$output) {
            try {
                $output[] = 'reading';
                yield $this->channel->read();
            } catch (ChannelClosedException $e) {
                $output[] = 'closed';
            }
        };

        $closer = function () use (&$output) {
            $output[] = 'closing';
            yield $this->channel->close();
        };

        $this->kernel->execute($reader());
        $this->kernel->execute($closer());
        $this->kernel->eventLoop()->run();

        $this->assertEquals(
            ['reading', 'closing', 'closed'],
            $output
        );
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
}

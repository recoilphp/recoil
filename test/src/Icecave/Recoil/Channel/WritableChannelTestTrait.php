<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;

trait WritableChannelTestTrait
{
    public function testCloseWithPendingWriter()
    {
        $output = [];

        $writer = function () use (&$output) {
            try {
                $output[] = 'writing';
                yield $this->channel->write('foo');
            } catch (ChannelClosedException $e) {
                $output[] = 'closed';
            }
        };

        $closer = function () use (&$output) {
            $output[] = 'closing';
            yield $this->channel->close();
        };

        $this->kernel->execute($writer());
        $this->kernel->execute($closer());
        $this->kernel->eventLoop()->run();

        $this->assertEquals(
            ['writing', 'closing', 'closed'],
            $output
        );
    }

    public function testWriteWhenClosed()
    {
        $writer = function () {
            yield $this->channel->close();
            $this->setExpectedException(ChannelClosedException::CLASS);
            yield $this->channel->write('foo');
        };

        $this->kernel->execute($writer());
        $this->kernel->eventLoop()->run();
    }
}

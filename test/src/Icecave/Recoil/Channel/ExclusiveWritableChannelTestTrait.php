<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;

trait ExclusiveWritableChannelTestTrait
{
    public function testWriteWhenLocked()
    {
        $writer = function () {
            $this->setExpectedException(ChannelLockedException::CLASS);
            yield $this->channel->write('foo');
        };

        $this->kernel->execute($this->channel->write('foo'));
        $this->kernel->execute($writer());
        $this->kernel->eventLoop()->run();
    }
}

<?php

namespace Recoil\Channel;

use Recoil\Channel\Exception\ChannelLockedException;
use Recoil\Recoil;

trait ExclusiveWritableChannelTestTrait
{
    public function testWriteWhenLocked()
    {
        Recoil::run(
            function () {
                $writer = function () {
                    $this->setExpectedException(ChannelLockedException::CLASS);
                    yield $this->channel->write('foo');
                };

                yield Recoil::execute($this->channel->write('foo'));
                yield Recoil::execute($writer());
            }
        );
    }
}

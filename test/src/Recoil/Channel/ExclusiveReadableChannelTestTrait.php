<?php

namespace Recoil\Channel;

use Recoil\Channel\Exception\ChannelLockedException;
use Recoil\Recoil;

trait ExclusiveReadableChannelTestTrait
{
    public function testReadWhenLocked()
    {
        Recoil::run(
            function () {
                $reader = function () {
                    $this->setExpectedException(ChannelLockedException::CLASS);
                    yield $this->channel->read();
                };

                yield Recoil::execute($this->channel->read());
                yield Recoil::execute($reader());
            }
        );
    }
}

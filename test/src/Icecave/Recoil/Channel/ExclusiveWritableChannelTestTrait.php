<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
use Icecave\Recoil\Recoil;

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

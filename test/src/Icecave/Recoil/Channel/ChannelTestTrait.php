<?php
namespace Icecave\Recoil\Channel;

use Icecave\Recoil\Recoil;

trait ChannelTestTrait
{
    public function testIsClosed()
    {
        Recoil::run(
            function () {
                $this->assertFalse($this->channel->isClosed());
                yield $this->channel->close();
                $this->assertTrue($this->channel->isClosed());
            }
        );
    }
}

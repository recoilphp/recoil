<?php
namespace Recoil\Channel;

use Recoil\Kernel\Kernel;
use Recoil\Recoil;
use PHPUnit_Framework_TestCase;

class ChannelTest extends PHPUnit_Framework_TestCase
{
    use ChannelTestTrait;
    use LoopbackChannelTestTrait;
    use ReadableChannelTestTrait;
    use WritableChannelTestTrait;
    use ExclusiveReadableChannelTestTrait;
    use ExclusiveWritableChannelTestTrait;

    public function setUp()
    {
        $this->kernel  = new Kernel;
        $this->channel = new Channel;
    }
}

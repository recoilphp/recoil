<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;
use PHPUnit_Framework_TestCase;

class ChannelTest extends PHPUnit_Framework_TestCase
{
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

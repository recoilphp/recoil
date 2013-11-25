<?php
namespace Icecave\Recoil\Channel\Exception;

use Exception;
use Icecave\Recoil\Channel\ChannelInterface;
use Phake;
use PHPUnit_Framework_TestCase;

class EngineExitExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $channel = Phake::mock(ChannelInterface::CLASS);
        $previous = new Exception;
        $exception = new ChannelClosedException($channel, $previous);

        $this->assertSame('Channel is closed.', $exception->getMessage());
        $this->assertSame($channel, $exception->channel());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

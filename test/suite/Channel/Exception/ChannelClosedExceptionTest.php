<?php

namespace Recoil\Channel\Exception;

use Exception;
use PHPUnit_Framework_TestCase;

class ChannelClosedExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $previous  = new Exception();
        $exception = new ChannelClosedException($previous);

        $this->assertSame('Channel is closed.', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

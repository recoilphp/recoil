<?php

namespace Recoil\Channel\Exception;

use Exception;
use PHPUnit_Framework_TestCase;

class ChannelLockedExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $previous  = new Exception();
        $exception = new ChannelLockedException($previous);

        $this->assertSame('Channel is already in use by another strand.', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

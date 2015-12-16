<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Eloquent\Phony\Phpunit\Phony;
use PHPUnit_Framework_TestCase;

class ApiCallTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->strand = Phony::mock(Strand::class);
        $this->caller = Phony::mock(Suspendable::class);
        $this->api = Phony::mock(Api::class);

        $this->subject = new ApiCall('<name>', [1, 2, 3]);
    }

    public function testAwait()
    {
        $this->subject->await(
            $this->strand->mock(),
            $this->caller->mock(),
            $this->api->mock()
        );

        $this->api->{'<name>'}->calledWith(
            $this->strand->mock(),
            $this->caller->mock(),
            1, 2, 3
        );
    }
}

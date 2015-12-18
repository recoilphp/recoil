<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use PHPUnit_Framework_TestCase;

class ApiCallTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $subject = new ApiCall('<name>', [1, 2, 3]);

        $this->assertSame('<name>',  $subject->name);
        $this->assertSame([1, 2, 3], $subject->arguments);
    }
}

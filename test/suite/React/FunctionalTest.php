<?php

declare (strict_types = 1);

namespace Recoil\React;

use PHPUnit_Framework_TestCase;
use Recoil\FunctionalTestTrait;

class FunctionalTest extends PHPUnit_Framework_TestCase
{
    use FunctionalTestTrait;

    public function setUp()
    {
        $this->kernel = new ReactKernel();
    }
}

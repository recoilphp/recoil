<?php

namespace Recoil\Stream;

use PHPUnit_Framework_TestCase;

class WritablePhpStreamTest extends PHPUnit_Framework_TestCase
{
    use WritableStreamTestTrait;

    public function createStream()
    {
        return new WritablePhpStream($this->resource);
    }
}

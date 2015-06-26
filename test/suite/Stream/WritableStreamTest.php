<?php
namespace Recoil\Stream;

use PHPUnit_Framework_TestCase;

class WritableStreamTest extends PHPUnit_Framework_TestCase
{
    use WritableStreamTestTrait;

    public function createStream()
    {
        return new WritableStream($this->resource);
    }
}

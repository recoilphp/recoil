<?php

namespace Recoil\Channel\Serialization;

use PHPUnit_Framework_TestCase;

class PhpSerializerTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->serializer = new PhpSerializer();
    }

    public function testSerialize()
    {
        $buffers = $this->serializer->serialize('foo bar');

        $this->assertSame(
            ["\x00\x00\x00\x0e", 's:7:"foo bar";'],
            iterator_to_array($buffers)
        );
    }
}

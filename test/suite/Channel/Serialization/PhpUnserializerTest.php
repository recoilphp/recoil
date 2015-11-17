<?php

namespace Recoil\Channel\Serialization;

use PHPUnit_Framework_TestCase;

class PhpUnserializerTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->unserializer = new PhpUnserializer();
    }

    public function testFeedMultipleValues()
    {
        $this->unserializer->feed(
            "\x00\x00\x00\x0a" . 's:3:"foo";' . "\x00\x00\x00\x0a" . 's:3:"bar";'
        );

        $this->assertSame('foo', $this->unserializer->unserialize());
        $this->assertSame('bar', $this->unserializer->unserialize());
    }

    public function testHasValue()
    {
        $this->assertFalse($this->unserializer->hasValue());

        // Feed part of the size ...
        $this->unserializer->feed("\x00\x00");

        $this->assertFalse($this->unserializer->hasValue());

        // The rest of the size ...
        $this->unserializer->feed("\x00\x0e");

        $this->assertFalse($this->unserializer->hasValue());

        // Feed part of the serialized value ...
        $this->unserializer->feed('s:7:"');

        $this->assertFalse($this->unserializer->hasValue());

        // The rest of the serialized value ...
        $this->unserializer->feed('foo bar";');

        $this->assertTrue($this->unserializer->hasValue());
    }

    public function testUnserialize()
    {
        $this->unserializer->feed("\x00\x00\x00\x0e" . 's:7:"foo bar";');
        $this->assertSame('foo bar', $this->unserializer->unserialize());
    }

    public function testUnserializeFalse()
    {
        $this->unserializer->feed("\x00\x00\x00\x04" . 'b:0;');
        $this->assertFalse($this->unserializer->unserialize());
    }

    public function testUnserializeFailureDueToInsuffientData()
    {
        $this->setExpectedException(
            'LogicException',
            'There is insufficient data to unserialize a value.'
        );

        $this->unserializer->unserialize();
    }

    public function testUnserializeFailure()
    {
        $this->unserializer->feed("\x00\x00\x00\x00");

        $this->setExpectedException(
            'RuntimeException',
            'An error occurred while unserializing the value.'
        );

        $this->unserializer->unserialize();
    }

    public function testFinalizeWithEmptyBuffer()
    {
        $this->unserializer->finalize();
        $this->assertTrue(true); // no exception thrown
    }

    public function testFinalizeWithReadyBuffer()
    {
        $this->unserializer->feed("\x00\x00\x00\x0e" . 's:7:"foo bar";');

        $this->unserializer->finalize();

        $this->assertTrue($this->unserializer->hasValue());
    }

    public function testFinalizeWithIncompleteSize()
    {
        $this->unserializer->feed("\x00\x00\x00\x0e" . 's:');

        $this->setExpectedException(
            'RuntimeException',
            'Data stream ended midway through unserialization.'
        );

        $this->unserializer->finalize();
    }
}

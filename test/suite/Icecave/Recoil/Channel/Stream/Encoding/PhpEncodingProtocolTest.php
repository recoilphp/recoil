<?php
namespace Icecave\Recoil\Channel\Stream\Encoding;

use PHPUnit_Framework_TestCase;

class PhpEncodingProtocolTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->encoding = new PhpEncodingProtocol;
    }

    public function testEncode()
    {
        $this->assertSame(
            "\x00\x00\x00\x0e" . 's:7:"foo bar";',
            $this->encoding->encode('foo bar')
        );
    }

    public function testIsReady()
    {
        $this->assertFalse($this->encoding->isReady());

        $packet = $this->encoding->encode('foo bar');

        // Feed part of the size ...
        $this->encoding->feed("\x00\x00");

        $this->assertFalse($this->encoding->isReady());

        // The rest of the size ...
        $this->encoding->feed("\x00\x0e");

        $this->assertFalse($this->encoding->isReady());

        // Feed part of the serialized value ...
        $this->encoding->feed('s:7:"');

        $this->assertFalse($this->encoding->isReady());

        // The rest of the serialized value ...
        $this->encoding->feed('foo bar";');

        $this->assertTrue($this->encoding->isReady());
    }

    public function testDecode()
    {
        $value = null;

        $this->assertFalse($this->encoding->decode($value));
        $this->assertNull($value);

        $this->encoding->feed("\x00\x00\x00\x0a" . 's:3:"foo";');
        $this->encoding->feed("\x00\x00\x00\x0a" . 's:3:"bar";');

        $this->assertTrue($this->encoding->decode($value));
        $this->assertSame('foo', $value);

        $this->assertTrue($this->encoding->decode($value));
        $this->assertSame('bar', $value);

        $this->assertFalse($this->encoding->decode($value));
    }

    public function testDecodeFalse()
    {
        $this->encoding->feed($this->encoding->encode(false));

        $value = null;
        $this->assertTrue($this->encoding->decode($value));
        $this->assertFalse($value);
    }

    public function testDecodeFailure()
    {
        $this->encoding->feed("\x00\x00\x00\x00");

        $this->setExpectedException(
            'RuntimeException',
            'An error occurred while unserializing the value.'
        );

        $this->encoding->decode($value);
    }
}

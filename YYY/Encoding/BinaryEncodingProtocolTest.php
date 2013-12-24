<?php
namespace Icecave\Recoil\Channel\Stream\Encoding;

use PHPUnit_Framework_TestCase;

class BinaryEncodingProtocolTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->encoding = new BinaryEncodingProtocol;
    }

    public function testEncode()
    {
        $this->assertSame('foo', $this->encoding->encode('foo'));
    }

    public function testEncodeFailure()
    {
        $this->setExpectedException('InvalidArgumentException', 'Value must be a string.');
        $this->encoding->encode(123);
    }

    public function testIsReady()
    {
        $this->assertFalse($this->encoding->isReady());

        $this->encoding->feed('f');

        $this->assertTrue($this->encoding->isReady());
    }

    public function testDecode()
    {
        $value = null;

        $this->assertFalse($this->encoding->decode($value));
        $this->assertNull($value);

        $this->encoding->feed('foo');
        $this->encoding->feed('bar');

        $this->assertTrue($this->encoding->decode($value));
        $this->assertSame('foobar', $value);

        $this->assertFalse($this->encoding->decode($value));
    }
}

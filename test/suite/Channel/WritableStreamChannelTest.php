<?php

namespace Recoil\Channel;

use PHPUnit_Framework_TestCase;
use Recoil\Channel\Serialization\PhpUnserializer;
use Recoil\Recoil;
use Recoil\Stream\WritablePhpStream;

class WritableStreamChannelTest extends PHPUnit_Framework_TestCase
{
    use ChannelTestTrait;
    use WritableChannelTestTrait;
    use ExclusiveWritableChannelTestTrait;

    public function setUp()
    {
        $this->path         = tempnam(sys_get_temp_dir(), 'recoil-');
        $this->resource     = fopen($this->path, 'w');
        $this->stream       = new WritablePhpStream($this->resource);
        $this->channel      = new WritableStreamChannel($this->stream);
        $this->unserializer = new PhpUnserializer();
    }

    public function tearDown()
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    public function testWrite()
    {
        Recoil::run(
            function () {
                yield $this->channel->write('foo');
                yield $this->channel->write('bar');
                yield $this->channel->close();
            }
        );

        $this->unserializer->feed(file_get_contents($this->path));

        $this->assertSame('foo', $this->unserializer->unserialize());
        $this->assertSame('bar', $this->unserializer->unserialize());
    }

    // public function prepareStream(array $values)
    // {
    //     foreach ($values as $value) {
    //         foreach ($this->serializer->serialize($value) as $buffer) {
    //             fwrite($this->resource, $buffer);
    //         }
    //     }

    //     rewind($this->resource);
    // }

    // public function testRead()
    // {
    //     $this->prepareStream(['foo', 'bar']);

    //     Recoil::run(
    //         function () {
    //             $values = [];
    //             while (!$this->channel->isClosed()) {
    //                 $values[] = (yield $this->channel->read());
    //             }

    //             $this->assertSame(['foo', 'bar'], $values);
    //         }
    //     );
    // }
}

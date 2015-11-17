<?php

namespace Recoil\Channel;

use PHPUnit_Framework_TestCase;
use Recoil\Channel\Serialization\PhpSerializer;
use Recoil\Recoil;
use Recoil\Stream\ReadablePhpStream;

class ReadableStreamChannelTest extends PHPUnit_Framework_TestCase
{
    use ChannelTestTrait;
    use ReadableChannelTestTrait;
    use ExclusiveReadableChannelTestTrait;

    public function setUp()
    {
        $this->path       = tempnam(sys_get_temp_dir(), 'recoil-');
        $this->resource   = fopen($this->path, 'r+');
        $this->stream     = new ReadablePhpStream($this->resource);
        $this->channel    = new ReadableStreamChannel($this->stream);
        $this->serializer = new PhpSerializer();
    }

    public function tearDown()
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    public function prepareStream(array $values)
    {
        foreach ($values as $value) {
            foreach ($this->serializer->serialize($value) as $buffer) {
                fwrite($this->resource, $buffer);
            }
        }

        rewind($this->resource);
    }

    public function testRead()
    {
        $this->prepareStream(['foo', 'bar']);

        Recoil::run(
            function () {
                $values = [];
                while (!$this->channel->isClosed()) {
                    $values[] = (yield $this->channel->read());
                }

                $this->assertSame(['foo', 'bar'], $values);
            }
        );
    }
}

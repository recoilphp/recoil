<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Serialization\PhpSerializer;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;
use Icecave\Recoil\Stream\ReadableStream;
use Phake;
use PHPUnit_Framework_TestCase;

class ReadableStreamChannelTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->path = tempnam(sys_get_temp_dir(), 'recoil-');
        $this->resource = fopen($this->path, 'r+');
        $this->stream = new ReadableStream($this->resource);
        $this->channel = new ReadableStreamChannel($this->stream);
        $this->serializer = new PhpSerializer;
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

    public function testReadWhenClosed()
    {
        Recoil::run(
            function () {
                yield $this->channel->close();
                $this->setExpectedException(ChannelClosedException::CLASS);
                yield $this->channel->read();
            }
        );
    }
}

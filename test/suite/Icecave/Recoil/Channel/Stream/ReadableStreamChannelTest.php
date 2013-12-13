<?php
namespace Icecave\Recoil\Channel\Stream;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\ExclusiveReadableChannelTestTrait;
use Icecave\Recoil\Channel\ReadableChannelTestTrait;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;
use Phake;
use PHPUnit_Framework_TestCase;
use React\EventLoop\StreamSelectLoop;
use React\Stream\Stream;

class ReadableStreamChannelTest extends PHPUnit_Framework_TestCase
{
    use ReadableChannelTestTrait;
    use ExclusiveReadableChannelTestTrait;

    public function setUp()
    {
        $this->eventLoop = Phake::partialMock(StreamSelectLoop::CLASS);
        $this->kernel    = new Kernel(null, null, null, $this->eventLoop);

        $this->handle = fopen(__FILE__, 'r+');

        stream_set_read_buffer($this->handle, 0);

        $this->stream = new Stream(
            $this->handle,
            $this->kernel->eventLoop()
        );

        $this->stream->bufferSize = 128;

        $this->channel = new ReadableStreamChannel($this->stream);
    }

    public function testRead()
    {
        $reader = function () {
            $content = '';

            while (!$this->channel->isClosed()) {
                $content .= (yield $this->channel->read());
            };

            $this->assertSame(file_get_contents(__FILE__), $content);
            $this->assertTrue($this->channel->isClosed());
        };

        $this->kernel->execute($reader());
        $this->kernel->eventLoop()->run();
    }

    public function testReadFailure()
    {
        $exception = new Exception('This is the exception.');

        // Emulate a stream error AFTER the read strand is suspended ...
        Phake::when($this->eventLoop)
            ->addReadStream(Phake::anyParameters())
            ->thenGetReturnByLambda(
                function () use ($exception) {
                    $this->stream->emit(
                        'error',
                        [$exception, $this->stream]
                    );
                }
            );

        $reader = function () use ($exception) {
            try {
                yield $this->channel->read();
            } catch (Exception $e) {
                $this->assertSame($exception, $e);
            }
        };

        $this->kernel->execute($reader());
        $this->kernel->eventLoop()->run();
    }

    public function testClosedWhileReading()
    {
        stream_get_contents($this->handle);

        $reader = function () {
            $this->setExpectedException(ChannelClosedException::CLASS);
            yield $this->channel->read();
        };

        $this->kernel->execute($reader());
        $this->kernel->eventLoop()->run();
    }
}

<?php
namespace Icecave\Recoil\Channel\Stream;

use Exception;
use Icecave\Recoil\Channel\ExclusiveWritableChannelTestTrait;
use Icecave\Recoil\Channel\WritableChannelTestTrait;
use Icecave\Recoil\Kernel\Kernel;
use Icecave\Recoil\Recoil;
use Phake;
use PHPUnit_Framework_TestCase;
use React\EventLoop\StreamSelectLoop;
use React\Stream\Stream;

class WritableStreamChannelTest extends PHPUnit_Framework_TestCase
{
    use WritableChannelTestTrait;
    use ExclusiveWritableChannelTestTrait;

    public function setUp()
    {
        $this->eventLoop = Phake::partialMock(StreamSelectLoop::CLASS);
        $this->kernel = new Kernel(null, null, null, $this->eventLoop);
        $this->filename = tempnam(sys_get_temp_dir(), 'recoil-');
        $this->handle = fopen($this->filename, 'r+');
        $this->stream = Phake::partialMock(
            Stream::CLASS,
            $this->handle,
            $this->kernel->eventLoop()
        );
        $this->stream->getBuffer()->softLimit = 1;
        $this->stream->pause();

        $this->channel = new WritableStreamChannel($this->stream);
    }

    public function testWrite()
    {
        $this->kernel->execute($this->channel->write('foo bar'));
        $this->kernel->eventLoop()->run();

        fseek($this->handle, 0);
        $content = stream_get_contents($this->handle);

        $this->assertSame('foo bar', $content);
    }

    public function testWriteWithImmediateResume()
    {
        $this->stream->getBuffer()->softLimit = 1024;

        $this->kernel->execute($this->channel->write('foo bar'));
        $this->kernel->eventLoop()->run();

        fseek($this->handle, 0);
        $content = stream_get_contents($this->handle);

        $this->assertSame('foo bar', $content);
    }

    public function testWriteParameterFailure()
    {
        $writer = function () {
            $this->setExpectedException('InvalidArgumentException', 'Value must be a string.');
            yield $this->channel->write(123);
        };

        $this->kernel->execute($writer());
        $this->kernel->eventLoop()->run();
    }

    public function testWriteFailure()
    {
        $exception = new Exception('This is the exception.');

        Phake::when($this->stream)
            ->write(Phake::anyParameters())
            ->thenGetReturnByLambda(
                function () use ($exception) {
                    $this->stream->emit(
                        'error',
                        [$exception, $this->stream]
                    );

                    return false;
                }
            );

        $writer = function () use ($exception) {
            try {
                yield $this->channel->write('foo');
            } catch (Exception $e) {
                $this->assertSame($exception, $e);
            }
        };

        $this->kernel->execute($writer());
        $this->kernel->eventLoop()->run();
    }

    public function testWriteFailureWhileNotSuspended()
    {
        $exception = new Exception('This is the exception.');

        $this->stream->emit(
            'error',
            [$exception, $this->stream]
        );

        $writer = function () use ($exception) {
            try {
                yield $this->channel->write('foo');
            } catch (Exception $e) {
                $this->assertSame($exception, $e);
            }
        };

        $this->kernel->execute($writer());
        $this->kernel->eventLoop()->run();
    }
}

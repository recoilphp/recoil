<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
use Icecave\Recoil\Channel\Serialization\PhpUnserializer;
use Icecave\Recoil\Channel\Serialization\UnserializerInterface;
use Icecave\Recoil\Recoil;
use Icecave\Recoil\Stream\ReadableStreamInterface;

class ReadableStreamChannel implements ReadableChannelInterface
{
    /**
     * @param ReadableStreamInterface    $stream       The underlying stream.
     * @param UnserializerInterface|null $unserializer The unserializer to use to convert stream data into values.
     * @param integer                    $bufferSize   The maximum number of bytes to read from the stream at a time.
     */
    public function __construct(
        ReadableStreamInterface $stream,
        UnserializerInterface $unserializer = null,
        $bufferSize = 8192
    ) {
        if (null === $unserializer) {
            $unserializer = new PhpUnserializer;
        }

        $this->stream = $stream;
        $this->unserializer = $unserializer;
        $this->bufferSize = $bufferSize;
    }

    public function read()
    {
        if ($this->isClosed()) {
            throw new ChannelClosedException;
        }

        while (!$this->unserializer->hasValue()) {
            $buffer = (yield $this->stream->read($this->bufferSize));
            $this->unserializer->feed($buffer);

            if ($this->stream->isClosed()) {
                $this->unserializer->finalize();
            }
        }

        yield Recoil::return_(
            $this->unserializer->unserialize()
        );
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * [CO-ROUTINE] Close this channel.
     *
     * Closing a channel indicates that no more values will be read from or
     * written to the channel. Any future read/write operations will fail.
     */
    public function close()
    {
        yield $this->stream->close();
    }

    /**
     * Check if this channel is closed.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return !$this->unserializer->hasValue()
            && $this->stream->isClosed();
    }

    private $stream;
    private $unserializer;
    private $bufferSize;
}

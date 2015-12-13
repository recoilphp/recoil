<?php

namespace Recoil\Channel;

use Recoil\Channel\Exception\ChannelClosedException;
use Recoil\Channel\Exception\ChannelLockedException;
use Recoil\Channel\Serialization\PhpUnserializer;
use Recoil\Channel\Serialization\Unserializer;
use Recoil\Recoil;
use Recoil\Stream\Exception\StreamClosedException;
use Recoil\Stream\Exception\StreamLockedException;
use Recoil\Stream\ReadableStream;

/**
 * A readable channel that unserializes values from a stream.
 */
class ReadableStreamChannel implements ReadableChannel
{
    /**
     * @param ReadableStream    $stream       The underlying stream.
     * @param Unserializer|null $unserializer The unserializer to use to convert stream data into values.
     * @param integer           $bufferSize   The maximum number of bytes to read from the stream at a time.
     */
    public function __construct(
        ReadableStream $stream,
        Unserializer $unserializer = null,
        $bufferSize = 8192
    ) {
        if (null === $unserializer) {
            $unserializer = new PhpUnserializer();
        }

        $this->stream       = $stream;
        $this->unserializer = $unserializer;
        $this->bufferSize   = $bufferSize;
    }

    /**
     * [COROUTINE] Read a value from this channel.
     *
     * Execution of the current strand is suspended until a value is available.
     *
     * If the channel is already closed, or is closed while a read operation is
     * pending a ChannelClosedException is thrown.
     *
     * Read operations must be exclusive only if the underlying stream requires
     * exclusive reads.
     *
     * @return mixed                  The value read from the channel.
     * @throws ChannelClosedException if the channel has been closed.
     * @throws ChannelLockedException if concurrent reads are unsupported.
     */
    public function read()
    {
        while (!$this->unserializer->hasValue()) {
            try {
                $buffer = (yield $this->stream->read($this->bufferSize));
            } catch (StreamClosedException $e) {
                throw new ChannelClosedException($e);
            } catch (StreamLockedException $e) {
                throw new ChannelLockedException($e);
            }

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
     * [COROUTINE] Close this channel.
     *
     * Closing a channel indicates that no more values will be read from or
     * written to the channel. Any future read/write operations will fail.
     */
    public function close()
    {
        try {
            yield $this->stream->close();
        } catch (StreamLockedException $e) {
            throw new ChannelLockedException($e);
        }
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

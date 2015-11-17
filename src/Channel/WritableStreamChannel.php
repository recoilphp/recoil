<?php

namespace Recoil\Channel;

use Recoil\Channel\Exception\ChannelClosedException;
use Recoil\Channel\Exception\ChannelLockedException;
use Recoil\Channel\Serialization\PhpSerializer;
use Recoil\Channel\Serialization\Serializer;
use Recoil\Stream\Exception\StreamClosedException;
use Recoil\Stream\Exception\StreamLockedException;
use Recoil\Stream\WritableStream;

/**
 * A writable channel that serializes values onto a stream.
 */
class WritableStreamChannel implements WritableChannel
{
    /**
     * @param WritableStream  $stream     The underlying stream.
     * @param Serializer|null $serializer The serializer used to convert values into stream data.
     */
    public function __construct(
        WritableStream $stream,
        Serializer $serializer = null
    ) {
        if (null === $serializer) {
            $serializer = new PhpSerializer();
        }

        $this->stream     = $stream;
        $this->serializer = $serializer;
    }

    /**
     * [COROUTINE] Write a value to this channel.
     *
     * Execution of the current strand is suspended until the value has been
     * consumed.
     *
     * If the channel is already closed, or is closed while a write operation is
     * pending a ChannelClosedException is thrown.
     *
     * Write operations must be exclusive only if the underlying stream requires
     * exlusive writes.
     *
     * @param mixed $value The value to write to the channel.
     *
     * @throws ChannelClosedException if the channel has been closed.
     * @throws ChannelLockedException if concurrent writes are unsupported.
     */
    public function write($value)
    {
        try {
            foreach ($this->serializer->serialize($value) as $buffer) {
                yield $this->stream->writeAll($buffer);
            }
        } catch (StreamClosedException $e) {
            throw new ChannelClosedException($e);
        } catch (StreamLockedException $e) {
            throw new ChannelLockedException($e);
        }
    }

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
        return $this->stream->isClosed();
    }

    private $stream;
    private $serializer;
}

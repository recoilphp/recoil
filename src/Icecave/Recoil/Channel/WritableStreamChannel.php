<?php
namespace Icecave\Recoil\Channel;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
use Icecave\Recoil\Channel\Serialization\PhpSerializer;
use Icecave\Recoil\Channel\Serialization\SerializerInterface;
use Icecave\Recoil\Recoil;
use Icecave\Recoil\Stream\WritableStreamInterface;
use InvalidArgumentException;

/**
 * A writable channel that serializes values onto a stream.
 */
class WritableStreamChannel implements WritableChannelInterface
{
    /**
     * @param WritableStreamInterface  $stream     The underlying stream.
     * @param SerializerInterface|null $serializer The serializer used to convert values into stream data.
     */
    public function __construct(
        WritableStreamInterface $stream,
        SerializerInterface $serializer = null
    ) {
        if (null === $serializer) {
            $serializer = new PhpSerializer;
        }

        $this->stream = $stream;
        $this->serializer = $serializer;
    }

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

    public function close()
    {
        try {
            yield $this->stream->close();
        } catch (StreamLockedException $e) {
            throw new ChannelLockedException($e);
        }
    }

    public function isClosed()
    {
        return $this->stream->isClosed();
    }

    public function serializer()
    {
        return $this->serializer;
    }

    private $stream;
    private $serializer;
}

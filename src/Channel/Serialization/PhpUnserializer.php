<?php

namespace Recoil\Channel\Serialization;

use LogicException;
use RuntimeException;
use SplQueue;

/**
 * An unserializer that uses the built-in PHP serialization protocol.
 */
class PhpUnserializer implements Unserializer
{
    public function __construct()
    {
        $this->buffer      = '';
        $this->packets     = new SplQueue();
        $this->falsePacket = serialize(false);
    }

    /**
     * Parse data received from the stream.
     *
     * @param string $buffer The data received from the stream.
     */
    public function feed($buffer)
    {
        $this->buffer .= $buffer;

        while (true) {
            $bufferSize = strlen($this->buffer);

            if ($bufferSize < self::PACKET_SIZE_LENGTH) {
                break;
            }

            list(, $packetSize) = unpack('N', $this->buffer);

            if ($bufferSize < self::PACKET_SIZE_LENGTH + $packetSize) {
                break;
            }

            $packet       = substr($this->buffer, self::PACKET_SIZE_LENGTH, $packetSize);
            $this->buffer = substr($this->buffer, self::PACKET_SIZE_LENGTH + $packetSize);
            $this->packets->push($packet);
        }
    }

    /**
     * Check if enough data has been received to parse a full value.
     *
     * @return boolean True if a value is available; otherwise, false.
     */
    public function hasValue()
    {
        return !$this->packets->isEmpty();
    }

    /**
     * Unserialize the next value.
     *
     * @return mixed            The decoded value.
     * @throws LogicException   if there is insufficient data to unserialize a value.
     * @throws RuntimeException if the data is unable to be unserialized.
     */
    public function unserialize()
    {
        if (!$this->hasValue()) {
            throw new LogicException(
                'There is insufficient data to unserialize a value.'
            );
        }

        $packet = $this->packets->dequeue();

        if ($packet === $this->falsePacket) {
            return false;
        }

        $value = unserialize($packet);

        if (false !== $value) {
            return $value;
        }

        throw new RuntimeException(
            'An error occurred while unserializing the value.'
        );
    }

    /**
     * Finalize the unserialization process.
     *
     * Indicates that no further calls will be made to feed() and asserts that
     * there is no partial values on the internal buffer.
     *
     * @throws RuntimeException if there are partial values on the internal buffer.
     */
    public function finalize()
    {
        if ($this->buffer) {
            throw new RuntimeException(
                'Data stream ended midway through unserialization.'
            );
        }
    }

    const PACKET_SIZE_LENGTH = 4;

    private $buffer;
    private $packets;
    private $falsePacket;
}

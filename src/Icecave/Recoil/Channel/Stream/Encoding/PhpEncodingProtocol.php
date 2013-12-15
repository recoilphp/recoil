<?php
namespace Icecave\Recoil\Channel\Stream\Encoding;

use RuntimeException;

/**
 * An encoding protocol based on PHP serialization.
 */
class PhpEncodingProtocol implements EncodingProtocolInterface
{
    public function __construct()
    {
        $this->buffer = '';
        $this->falsePacket = serialize(false);
    }

    /**
     * Encode a value to a string.
     *
     * @param mixed $value The value to encode.
     *
     * @return string                   The encoded value.
     * @throws InvalidArgumentException if the value can not be encoded.
     */
    public function encode($value)
    {
        $buffer = serialize($value);

        return pack('N', strlen($buffer)) . $buffer;
    }

    /**
     * Feed received data into the decode buffer.
     *
     * @param string $buffer The data received from the stream.
     */
    public function feed($buffer)
    {
        $this->buffer .= $buffer;
    }

    /**
     * Check if the buffer contains enough data for a value to be decoded.
     *
     * @return boolean True if a value can be decoded.
     */
    public function isReady()
    {
        if (null === $this->size) {
            if (strlen($this->buffer) < 4) {
                return false;
            }

            $buffer = $this->consume(4);
            list(, $this->size) = unpack('N', $buffer);
        }

        return strlen($this->buffer) >= $this->size;
    }

    /**
     * Attempt to decode a value from the internal buffer.
     *
     * @param mixed &$value Assigned the decoded value.
     *
     * @return boolean True if a value as successfully decoded.
     */
    public function decode(&$value)
    {
        if (!$this->isReady()) {
            return false;
        }

        $packet = $this->consume($this->size);
        $this->size = null;

        if ($packet === $this->falsePacket) {
            $value = false;
        } else {
            $value = unserialize($packet);

            if (false === $value) {
                throw new RuntimeException(
                    'An error occurred while unserializing the value.'
                );
            }
        }

        return true;
    }

    /**
     * Consume data from the front of the internal buffer.
     *
     * @param integer $bytes The number of bytes to consume.
     *
     * @return string The first $bytes bytes from the front of the internal buffer.
     */
    private function consume($bytes)
    {
        $buffer       = substr($this->buffer, 0, $bytes);
        $this->buffer = substr($this->buffer, $bytes) ?: '';

        return $buffer;
    }

    private $size;
    private $buffer;
    private $falsePacket;
}

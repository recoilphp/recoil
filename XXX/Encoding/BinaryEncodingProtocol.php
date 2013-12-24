<?php
namespace Icecave\Recoil\Channel\Stream\Encoding;

use InvalidArgumentException;

/**
 * An encoding protocol for binary data.
 *
 * This protocol essentially passes PHP strings to and from the stream unchanged.
 */
class BinaryEncodingProtocol implements EncodingProtocolInterface
{
    public function __construct()
    {
        $this->buffer = '';
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
        if (!is_string($value)) {
            throw new InvalidArgumentException('Value must be a string.');
        }

        return $value;
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
        return strlen($this->buffer) > 0;
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

        $value = $this->buffer;
        $this->buffer = '';

        return true;
    }

    private $buffer;
}

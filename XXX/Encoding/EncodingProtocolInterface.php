<?php
namespace Icecave\Recoil\Channel\Stream\Encoding;

use InvalidArgumentException;

/**
 * Defines a mechanism for encoding PHP values on stream-based channels.
 */
interface EncodingProtocolInterface
{
    /**
     * Encode a value to a string.
     *
     * @param mixed $value The value to encode.
     *
     * @return string                   The encoded value.
     * @throws InvalidArgumentException if the value can not be encoded.
     */
    public function encode($value);

    /**
     * Feed received data into the decode buffer.
     *
     * @param string $buffer The data received from the stream.
     */
    public function feed($buffer);

    /**
     * Check if the buffer contains enough data for a value to be decoded.
     *
     * @return boolean True if a value can be decoded.
     */
    public function isReady();

    /**
     * Attempt to decode a value from the internal buffer.
     *
     * @param mixed &$value Assigned the decoded value.
     *
     * @return boolean True if a value as successfully decoded.
     */
    public function decode(&$value);
}

<?php

namespace Recoil\Channel\Serialization;

use LogicException;
use RuntimeException;

/**
 * A mechanism for unserializing PHP values from stream-based channels.
 */
interface Unserializer
{
    /**
     * Parse data received from the stream.
     *
     * @param string $buffer The data received from the stream.
     */
    public function feed($buffer);

    /**
     * Check if enough data has been received to parse a full value.
     *
     * @return boolean True if a value is available; otherwise, false.
     */
    public function hasValue();

    /**
     * Unserialize the next value.
     *
     * @return mixed            The decoded value.
     * @throws LogicException   if there is insufficient data to unserialize a value.
     * @throws RuntimeException if the data is unable to be unserialized.
     */
    public function unserialize();

    /**
     * Finalize the unserialization process.
     *
     * Indicates that no further calls will be made to feed() and asserts that
     * there is no partial values on the internal buffer.
     *
     * @throws RuntimeException if there are partial values on the internal buffer.
     */
    public function finalize();
}

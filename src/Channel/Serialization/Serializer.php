<?php

namespace Recoil\Channel\Serialization;

use InvalidArgumentException;

/**
 * A mechanism for serializing PHP values on stream-based channels.
 */
interface Serializer
{
    /**
     * Serialize a value to a string.
     *
     * @param mixed $value The value to encode.
     *
     * @return mixed<string>            A sequence of string buffers containing the serialized value.
     * @throws InvalidArgumentException if the value can not be encoded.
     */
    public function serialize($value);
}

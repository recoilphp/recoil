<?php

namespace Recoil\Channel\Serialization;

use InvalidArgumentException;

/**
 * A serializer that uses the built-in PHP serialization protocol.
 */
class PhpSerializer implements Serializer
{
    /**
     * Serialize a value to a string.
     *
     * @param mixed $value The value to encode.
     *
     * @return mixed<string>            A sequence of string buffers containing the serialized value.
     * @throws InvalidArgumentException if the value can not be encoded.
     */
    public function serialize($value)
    {
        $packet = serialize($value);

        yield pack('N', strlen($packet));
        yield $packet;
    }
}

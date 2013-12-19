<?php
namespace Icecave\Recoil\Stream;

interface ReadableStreamInterface
{
    /**
     * [CO-ROUTINE] Read data from the stream.
     *
     * The implementation MUST suspend execution of the current strand until
     * data is available.
     *
     * If the stream is already closed, or is closed while a read operation is
     * pending the implementation MUST throw a StreamClosedException.
     *
     * The implementation MAY require read operations to be exclusive. If
     * concurrent reads are attempted but not supported the implementation MUST
     * throw a StreamLockedException.
     *
     * @param integer $length The maximum number of bytes to read.
     *
     * @return string                The data read from the stream.
     * @throws StreamClosedException if the stream is already closed.
     * @throws StreamLockedException if concurrent reads are unsupported.
     * @throws StreamReadException   if an error occurs while reading from the stream.
     */
    public function read($length);

    /**
     * [CO-ROUTINE] Close this stream.
     *
     * Closing a stream indicates that no more data will be read from the
     * stream. Any future read operations will fail.
     */
    public function close();

    /**
     * Check if this stream is closed.
     *
     * @return boolean True if the stream has been closed; otherwise, false.
     */
    public function isClosed();
}

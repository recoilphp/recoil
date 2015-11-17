<?php

namespace Recoil\Stream;

use Recoil\Stream\Exception\StreamClosedException;
use Recoil\Stream\Exception\StreamLockedException;
use Recoil\Stream\Exception\StreamReadException;

/**
 * Interface and specification for coroutine based readable streams.
 *
 * The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
 * "SHOULD NOT", "RECOMMENDED",  "MAY", and "OPTIONAL" in this document are to
 * be interpreted as described in RFC 2119.
 *
 * @link http://www.ietf.org/rfc/rfc2119.txt
 */
interface ReadableStream
{
    /**
     * [COROUTINE] Read data from the stream.
     *
     * The implementation MUST suspend execution of the current strand until
     * data is available or the end of the data stream is reached. Execution
     * MAY be resumed before $length bytes have been read.
     *
     * If the stream is already closed a StreamClosedException MUST be thrown.
     *
     * If the end of the data stream is reached the implementation MUST close
     * the stream such that future invocations throw a StreamClosedException and
     * isClosed() returns true.
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
     * [COROUTINE] Close this stream.
     *
     * Closing a stream indicates that no more data will be read from the
     * stream. Once a stream is closed future, invocations of read() MUST throw
     * a StreamClosedException.
     *
     * The implementation SHOULD NOT throw an exception if close() is called on
     * an already-closed stream.
     *
     * The implementation SHOULD support closing the stream while a read
     * operation is in progress, otherwise StreamLockedException MUST be thrown.
     *
     * @throws StreamLockedException if the stream can not be closed due to a pending read operation.
     */
    public function close();

    /**
     * Check if this stream is closed.
     *
     * The implementation MUST return true after close() has been called or if
     * the end of the data stream has been reached.
     *
     * @return boolean True if the stream has been closed; otherwise, false.
     */
    public function isClosed();
}

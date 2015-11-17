<?php

namespace Recoil\Stream;

use Recoil\Stream\Exception\StreamClosedException;
use Recoil\Stream\Exception\StreamLockedException;
use Recoil\Stream\Exception\StreamReadException;
use Recoil\Stream\Exception\StreamWriteException;

/**
 * Adapts separate read/write streams into a bidirectional stream.
 */
class BidirectionalStreamAdaptor implements BidirectionalStream
{
    public function __construct(
        ReadableStream $readStream,
        WritableStream $writeStream
    ) {
        $this->readStream  = $readStream;
        $this->writeStream = $writeStream;
    }

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
    public function read($length)
    {
        return $this->readStream->read($length);
    }

    /**
     * [COROUTINE] Write data to this stream.
     *
     * The implementation MAY suspend execution of the current strand until the
     * data is sent.
     *
     * If the stream is already closed, or is closed while a write operation is
     * pending the implementation MUST throw a StreamClosedException.
     *
     * The implementation MAY require write operations to be exclusive. If
     * concurrent writes are attempted but not supported the implementation MUST
     * throw a StreamLockedException.
     *
     * @param string       $buffer The data to write to the stream.
     * @param integer|null $length The maximum number of bytes to write.
     *
     * @return integer               The number of bytes written.
     * @throws StreamClosedException if the stream is already closed.
     * @throws StreamLockedException if concurrent writes are unsupported.
     * @throws StreamWriteException  if an error occurs while writing to the stream.
     */
    public function write($buffer, $length = null)
    {
        return $this->writeStream->write($buffer, $length);
    }

    /**
     * [COROUTINE] Write all data from the given buffer to this stream.
     *
     * The implementation MAY suspend execution of the current strand until the
     * data is sent.
     *
     * If the stream is already closed, or is closed while a write operation is
     * pending the implementation MUST throw a StreamClosedException.
     *
     * The implementation MAY require write operations to be exclusive. If
     * concurrent writes are attempted but not supported the implementation MUST
     * throw a StreamLockedException.
     *
     * @param string       $buffer The data to write to the stream.
     * @param integer|null $length The maximum number of bytes to write.
     *
     * @throws StreamClosedException if the stream is already closed.
     * @throws StreamLockedException if concurrent writes are unsupported.
     * @throws StreamWriteException  if an error occurs while writing to the stream.
     */
    public function writeAll($buffer)
    {
        return $this->writeStream->writeAll($buffer);
    }

    /**
     * [COROUTINE] Close this stream.
     *
     * Closing a stream indicates that no more data will be written. Once a
     * stream is closed future invocations of write() MUST throw
     * a StreamClosedException.
     *
     * The implementation SHOULD NOT throw an exception if close() is called on
     * an already-closed stream.
     *
     * The implementation SHOULD support closing the stream while a write
     * operation is in progress, otherwise StreamLockedException MUST be thrown.
     *
     * @throws StreamLockedException if the stream can not be closed due to a pending write operation.
     */
    public function close()
    {
        yield $this->readStream->close();
        yield $this->writeStream->close();
    }

    /**
     * Check if this stream is closed.
     *
     * The implementation MUST return true after close() has been called or the
     * stream is closed during a write operation.
     *
     * @return boolean True if the stream has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return $this->readStream->isClosed()
            || $this->writeStream->isClosed();
    }

    private $readStream;
    private $writeStream;
}

<?php
namespace Icecave\Recoil\Stream;

use Icecave\Recoil\Stream\Exception\StreamClosedException;
use Icecave\Recoil\Stream\Exception\StreamLockedException;
use Icecave\Recoil\Stream\Exception\StreamWriteException;

/**
 * Interface and specification for co-routine based writable streams.
 *
 * The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
 * "SHOULD NOT", "RECOMMENDED",  "MAY", and "OPTIONAL" in this document are to
 * be interpreted as described in RFC 2119.
 *
 * @link http://www.ietf.org/rfc/rfc2119.txt
 */
interface WritableStreamInterface
{
    /**
     * [CO-ROUTINE] Write data to this stream.
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
    public function write($buffer, $length = null);

    /**
     * [CO-ROUTINE] Write all data from the given buffer to this stream.
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
    public function writeAll($buffer);

    /**
     * [CO-ROUTINE] Close this stream.
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
    public function close();

    /**
     * Check if this stream is closed.
     *
     * The implementation MUST return true after close() has been called or the
     * stream is closed during a write operation.
     *
     * @return boolean True if the stream has been closed; otherwise, false.
     */
    public function isClosed();
}

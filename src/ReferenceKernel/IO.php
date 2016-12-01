<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 *
 * IO invokes callbacks when streams become readable and/or writable.
 */
final class IO
{
    /**
     * Register a callback to be invoked when a stream becomes readable.
     *
     * Each time the stream becomes ready, only the callback that was
     * registered the earliest is called.
     *
     * @param resource $stream The stream to read.
     * @param callable $fn     The function to invoke.
     *
     * @return callable A function used to remove the callback.
     */
    public function read($stream, callable $fn): callable
    {
        assert(is_resource($stream));

        $id = ++$this->nextId;
        $fd = (int) $stream;

        $this->readStreams[$fd] = $stream;
        $this->readQueue[$fd][$id] = $fn;

        return function () use ($fd, $id) {
            $queue = &$this->readQueue[$fd];
            unset($queue[$id]);

            if (empty($queue)) {
                unset(
                    $this->readStreams[$fd],
                    $this->readQueue[$fd]
                );
            }
        };
    }

    /**
     * Register a callback to be invoked when a stream becomes writable.
     *
     * Each time the stream becomes ready, only the callback that was
     * registered the earliest is called.
     *
     * @param resource $stream The stream to write.
     * @param callable $fn     The function to invoke.
     *
     * @return callable A function used to remove the callback.
     */
    public function write($stream, callable $fn): callable
    {
        assert(is_resource($stream));

        $id = ++$this->nextId;
        $fd = (int) $stream;
        $this->writeStreams[$fd] = $stream;
        $this->writeQueue[$fd][$id] = $fn;

        return function () use ($fd, $id) {
            $queue = &$this->writeQueue[$fd];
            unset($queue[$id]);

            if (empty($queue)) {
                unset(
                    $this->writeStreams[$fd],
                    $this->writeQueue[$fd]
                );
            }
        };
    }

    /**
     * Wait for streams to become ready for reading and/or writing.
     *
     * @param int|null The maximum time to wait for IO, in microseconds (null = forever).

     * @return bool True if there is more IO to perform.
     */
    public function tick(int $timeout = null) : bool
    {
        if (
            empty($this->readStreams) &&
            empty($this->writeStreams)
        ) {
            return false;
        }

        $readStreams = $this->readStreams;
        $writeStreams = $this->writeStreams;
        $exceptStreams = null;

        $count = @\stream_select(
            $readStreams,
            $writeStreams,
            $exceptStreams,
            $timeout === null ? null : 0,
            $timeout ?: 0
        );

        if ($count === false) {
            // TODO: ...
            assert(false, 'error in stream select (signal interrupt?)');

            return true;
        }

        foreach ($readStreams as $stream) {
            $queue = $this->readQueue[(int) $stream] ?? [];
            foreach ($queue as $fn) {
                $fn($stream);
                break;
            }
        }

        foreach ($writeStreams as $stream) {
            $queue = $this->writeQueue[(int) $stream] ?? [];
            foreach ($queue as $fn) {
                $fn($stream);
                break;
            }
        }

        return !empty($this->readStreams) ||
               !empty($this->writeStreams);
    }

    /**
     * @param int A sequence of IDs used to identify registered callbacks.
     */
    private $nextId = 0;

    /**
     * @param array<int, stream> A map of resource ID to stream for reading.
     */
    private $readStreams = [];

    /**
     * @param array<int, array<int, callable>> A map of resource ID to a queue
     *                                         of callback functions for that stream.
     */
    private $readQueue = [];

    /**
     * @param array<int, stream> A map of resource ID to stream for writing.
     */
    private $writeStreams = [];

    /**
     * @param array<int, array<int, callable>> A map of resource ID to a queue
     *                                         of callback functions for that stream.
     */
    private $writeQueue = [];
}

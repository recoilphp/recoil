<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

use RuntimeException;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 * @final
 *
 * IO invokes callbacks when streams become readable and/or writable.
 */
class IO
{
    const INACTIVE = 0;
    const ACTIVE = 1;
    const INTERRUPT = 2;

    /**
     * Fire a callback when any of the given streams become ready for reading
     * or writing.
     *
     * @param array<resource> $read
     * @param array<resource> $write
     * @param callable        $fn
     */
    public function select(
        array $read,
        array $write,
        callable $fn
    ): callable {
        $select = new IOSelect(
            ++$this->nextId,
            $read,
            $write,
            $fn
        );

        $this->selects[$select->id] = $select;

        foreach ($select->read as $fd => $stream) {
            $this->readStreams[$fd] = $stream;
            $this->readQueue[$fd][$select->id] = $select;
        }

        foreach ($select->write as $fd => $stream) {
            $this->writeStreams[$fd] = $stream;
            $this->writeQueue[$fd][$select->id] = $select;
        }

        return function () use ($select) {
            unset($this->selects[$select->id]);

            foreach ($select->read as $fd => $stream) {
                $queue = &$this->readQueue[$fd];

                unset($queue[$select->id]);

                if (empty($queue)) {
                    unset(
                        $this->readStreams[$fd],
                        $this->readQueue[$fd]
                    );
                }
            }

            foreach ($select->write as $fd => $stream) {
                $queue = &$this->writeQueue[$fd];

                unset($queue[$select->id]);

                if (empty($queue)) {
                    unset(
                        $this->writeStreams[$fd],
                        $this->writeQueue[$fd]
                    );
                }
            }
        };
    }

    /**
     * Wait for streams to become ready for reading and/or writing.
     *
     * @param int|null The maximum time to wait for IO, in microseconds (null = forever).

     * @return int One of the ACTIVE, INACTIVE or INTERRUPTED constants.
     */
    public function tick(int $timeout = null): int
    {
        if (
            empty($this->readStreams) &&
            empty($this->writeStreams)
        ) {
            if ($timeout !== null) {
                \usleep($timeout);
            }

            return self::INACTIVE;
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

        // @codeCoverageIgnoreStart
        if ($count === false) {
            $error = \error_get_last();

            if ($error === null) {
                // Handle cases where stream_select() returns false, but there
                // is no error information. This seems to occur when in-memory
                // streams are selected, but we can't guarantee that's the
                // actual reason ...
                throw new RuntimeException(
                    'An unknown error occurred while waiting for stream activity.'
                );
            }

            if (\stripos($error['message'], 'interrupted system call') === false) {
                throw new ErrorException(
                   $error['message'],
                   $error['type'],
                   1, // severity
                   $error['file'],
                   $error['line']
               );
            }

            return self::INTERRUPT;
        }
        // @codeCoverageIgnoreEnd

        $ready = [];
        $readyForRead = [];
        $readyForWrite = [];

        foreach ($readStreams as $stream) {
            $fd = (int) $stream;
            $queue = $this->readQueue[$fd] ?? [];

            foreach ($queue as $select) {
                $ready[$select->id] = $select;
                $readyForRead[$select->id][] = $stream;
                break;
            }
        }

        foreach ($writeStreams as $stream) {
            $fd = (int) $stream;
            $queue = $this->writeQueue[$fd] ?? [];

            foreach ($queue as $select) {
                $ready[$select->id] = $select;
                $readyForWrite[$select->id][] = $stream;
                break;
            }
        }

        foreach ($ready as $select) {
            ($select->callback)(
                $readyForRead[$select->id] ?? [],
                $readyForWrite[$select->id] ?? []
            );
        }

        if (
            empty($this->readStreams) &&
            empty($this->writeStreams)
        ) {
            return self::INACTIVE;
        }

        return self::ACTIVE;
    }

    /**
     * @var int A sequence of IDs used to identify registered callbacks.
     */
    private $nextId = 0;

    /**
     * @var array<int, IOSelect> A map of select ID to IOSelect object.
     */
    private $selects = [];

    /**
     * @var array<int, stream> A map of resource ID to stream for reading.
     */
    private $readStreams = [];

    /**
     * @var array<int, array<int, IOSelect>> A map of resource ID to a queue
     *                 of IOSelect objects for that stream.
     */
    private $readQueue = [];

    /**
     * @var array<int, stream> A map of resource ID to stream for writing.
     */
    private $writeStreams = [];

    /**
     * @var array<int, array<int, IOSelect>> A map of resource ID to a queue
     *                 of IOSelect objects for that stream.
     */
    private $writeQueue = [];
}

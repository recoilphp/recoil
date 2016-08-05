<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

/**
 * @access private
 */
final class IO
{
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

    private $nextId = 0;
    private $readStreams = [];
    private $readQueue = [];
    private $writeStreams = [];
    private $writeQueue = [];
}

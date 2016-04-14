<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Recoil\Exception\TerminatedException;
use Recoil\Exception\TimeoutException;
use Recoil\Kernel\Api;
use Recoil\Kernel\Awaitable;
use Recoil\Kernel\Strand;
use Recoil\Kernel\StrandObserver;
use Throwable;

/**
 * Please note that this code is not part of the public API. It may be
 * changed or removed at any time without notice.
 *
 * @access private
 *
 * Provides exclusive, queued access to streams.
 */
final class StreamQueue
{
    /**
     * @param LoopInterface $eventLoop The event loop.
     */
    public function __construct(LoopInterface $eventLoop)
    {
        $this->eventLoop = $eventLoop;

        $this->read = function ($stream) {
            foreach ($this->reading[(int) $stream] as $callback) {
                break;
            }

            $callback($stream);
        };

        $this->write = function ($stream) {
            foreach ($this->writing[(int) $stream] as $callback) {
                break;
            }

            $callback($stream);
        };
    }

    /**
     * Add a callback to the queue of read handlers for a given stream.
     *
     * The callback at the head of the queue is invoked whenever the stream
     * becomes ready. It is passed the stream as its only argument.
     *
     * The return value of this method is a function used to remove the callback
     * from the queue for this stream.
     *
     * @param resource $stream   The stream to wait for.
     * @param callable $callback The callback to invoke when the stream is ready.
     *
     * @return callable A function that removes the callback from the queue.
     */
    public function read($stream, callable $callback) : callable
    {
        assert(\is_resource($stream));

        $fd = (int) $stream;
        $id = $this->nextId++;

        if (empty($this->reading[$fd])) {
            $this->reading[$fd] = [$id => $callback];
            $this->eventLoop->addReadStream($stream, $this->read);
        } else {
            $this->reading[$fd][$id] = $callback;
        }

        return function () use ($stream, $fd, $id) {
            unset($this->reading[$fd][$id]);
            if (empty($this->reading[$fd])) {
                $this->eventLoop->removeReadStream($stream);
                unset($this->reading[$fd]);
            }
        };
    }

    /**
     * Add a callback to the queue of write handlers for a given stream.
     *
     * The callback at the head of the queue is invoked whenever the stream
     * becomes ready. It is passed the stream as its only argument.
     *
     * The return value of this method is a function used to remove the callback
     * from the queue for this stream.
     *
     * @param resource $stream   The stream to wait for.
     * @param callable $callback The callback to invoke when the stream is ready.
     *
     * @return callable A function that removes the callback from the queue.
     */
    public function write($stream, callable $callback) : callable
    {
        assert(\is_resource($stream));

        $fd = (int) $stream;
        $id = $this->nextId++;

        if (empty($this->writing[$fd])) {
            $this->writing[$fd] = [$id => $callback];
            $this->eventLoop->addWriteStream($stream, $this->write);
        } else {
            $this->writing[$fd][$id] = $callback;
        }

        return function () use ($stream, $fd, $id) {
            unset($this->writing[$fd][$id]);
            if (empty($this->writing[$fd])) {
                $this->eventLoop->removeWriteStream($stream);
                unset($this->writing[$fd]);
            }
        };
    }

    /**
     * @var int The ID of the next callback to be enqueued. Used as an index
     *          into the queue to allow fast removal upon completion.
     */
    public $nextId = 1;

    /**
     * @var array<int, array<callable>> A map of file-descriptor to FIFO queue
     *                                  of read callbacks for that stream.
     */
    public $reading = [];

    /**
     * @var array<int, array<callable>> A map of file-descriptor to FIFO queue
     *                                  of write callbacks for that stream.
     */
    public $writing = [];

    /**
     * @var Closure The underlying stream read handler that is registered with
     *              the React event loop.
     */
    public $read;

    /**
     * @var Closure The underlying stream write handler that is registered with
     *              the React event loop.
     */
    public $write;
}

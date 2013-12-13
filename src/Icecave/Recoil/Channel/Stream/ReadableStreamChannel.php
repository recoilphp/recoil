<?php
namespace Icecave\Recoil\Channel\Stream;

use Exception;
use Icecave\Recoil\Channel\Exception\ChannelClosedException;
use Icecave\Recoil\Channel\Exception\ChannelLockedException;
use Icecave\Recoil\Channel\ReadableChannelInterface;
use Icecave\Recoil\Recoil;
use React\Stream\ReadableStreamInterface;

/**
 * Adapts a ReactPHP readable stream into a readable channel.
 *
 * Channel read operations produce strings read from the stream.
 *
 * The channel is closed when the stream 'end' event is emitted.
 */
class ReadableStreamChannel implements ReadableChannelInterface
{
    public function __construct(ReadableStreamInterface $stream)
    {
        $this->stream = $stream;

        $this->stream->on('data',  [$this, 'onStreamData']);
        $this->stream->on('end',   [$this, 'onStreamEnd']);
        $this->stream->on('error', [$this, 'onStreamError']);

        // Do not read any data until a co-routine suspends waiting for data.
        $this->stream->pause();
    }

    /**
     * [CO-ROUTINE] Read a value from this channel.
     *
     * Execution of the current strand is suspended until a value is available.
     *
     * If the channel is already closed, or is closed while a read operation is
     * pending a ChannelClosedException is thrown.
     *
     * Read operations must be exclusive. If concurrent reads are attempted
     * a ChannelLockedException is thrown.
     *
     * @return string                 The value read from the channel.
     * @throws ChannelClosedException if the channel has been closed.
     * @throws ChannelLockedException if concurrent reads are attempted.
     */
    public function read()
    {
        if ($this->isClosed()) {
            throw new ChannelClosedException($this);
        } elseif ($this->readStrand) {
            throw new ChannelLockedException($this);
        }

        $value = (yield Recoil::suspend(
            function ($strand) {
                $this->readStrand = $strand;
                $this->stream->resume();
            }
        ));

        yield Recoil::return_($value);
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    /**
     * [CO-ROUTINE] Close this channel.
     *
     * Closing a channel indicates that no more values will be read from or
     * written to the channel. Any future read/write operations will fail.
     */
    public function close()
    {
        $this->stream->close();

        yield Recoil::noop();
    }

    /**
     * Check if this channel is closed.
     *
     * @return boolean True if the channel has been closed; otherwise, false.
     */
    public function isClosed()
    {
        return !$this->stream->isReadable();
    }

    /**
     * @internal
     */
    public function onStreamData($data)
    {
        $this->stream->pause();

        if ($data) {
            $this->readStrand->resumeWithValue($data);
            $this->readStrand = null;
        }
    }

    /**
     * @internal
     */
    public function onStreamEnd()
    {
        $this->stream->removeListener('data',  [$this, 'onStreamData']);
        $this->stream->removeListener('end',   [$this, 'onStreamEnd']);
        $this->stream->removeListener('error', [$this, 'onStreamError']);

        if ($this->readStrand) {
            $this->readStrand->resumeWithException(
                new ChannelClosedException($this)
            );
            $this->readStrand = null;
        }
    }

    /**
     * @internal
     */
    public function onStreamError(Exception $exception)
    {
        $this->readStrand->resumeWithException($exception);
        $this->readStrand = null;
    }

    private $stream;
    private $readStrand;
}

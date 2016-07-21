<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use ErrorException;
use React\EventLoop\LoopInterface;
use Recoil\Exception\TimeoutException;
use Recoil\Kernel\Api;
use Recoil\Kernel\ApiTrait;
use Recoil\Kernel\Strand;

/**
 * A kernel API based on the React event loop.
 */
final class ReactApi implements Api
{
    /**
     * @param LoopInterface $eventLoop The event loop.
     */
    public function __construct(
        LoopInterface $eventLoop,
        StreamQueue $streamQueue = null
    ) {
        $this->eventLoop = $eventLoop;
        $this->streamQueue = $streamQueue ?: new StreamQueue($eventLoop);
    }

    /**
     * Allow other strands to execute before resuming the calling strand.
     *
     * @param Strand $strand The strand executing the API call.
     */
    public function cooperate(Strand $strand)
    {
        $this->eventLoop->futureTick(
            static function () use ($strand) {
                $strand->send(null, $strand);
            }
        );
    }

    /**
     * Suspend the calling strand for a fixed interval.
     *
     * @param Strand $strand  The strand executing the API call.
     * @param float  $seconds The interval to wait.
     */
    public function sleep(Strand $strand, float $seconds)
    {
        if ($seconds > 0) {
            $timer = $this->eventLoop->addTimer(
                $seconds,
                static function () use ($strand) {
                    $strand->send(null, $strand);
                }
            );

            $strand->setTerminator(
                static function () use ($timer) {
                    $timer->cancel();
                }
            );
        } else {
            $this->eventLoop->futureTick(
                static function () use ($strand) {
                    $strand->send(null, $strand);
                }
            );
        }
    }

    /**
     * Execute a coroutine on a new strand that is terminated after a timeout.
     *
     * If the strand does not exit within the specified time it is terminated
     * and the calling strand is resumed with a {@see TimeoutException}.
     * Otherwise, it is resumed with the value or exception produced by the
     * coroutine.
     *
     * @param Strand $strand    The strand executing the API call.
     * @param float  $seconds   The interval to allow for execution.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function timeout(Strand $strand, float $seconds, $coroutine)
    {
        $substrand = $strand->kernel()->execute($coroutine);

        (new StrandTimeout($this->eventLoop, $seconds, $substrand))->await($strand, $this);
    }

    /**
     * Read data from a stream resource, blocking until a specified amount of
     * data is available.
     *
     * Data is buffered until it's length falls between $minLength and
     * $maxLength, or the stream reaches EOF. The calling strand is resumed with
     * a string containing the buffered data.
     *
     * $minLength and $maxLength may be equal to fill a fixed-size buffer.
     *
     * If the stream is already being read by another strand, no data is
     * read until the other strand's operation is complete.
     *
     * Similarly, for the duration of the read, calls to {@see Api::select()}
     * will not indicate that the stream is ready for reading.
     *
     * It is assumed that the stream is already configured as non-blocking.
     *
     * @param Strand   $strand    The strand executing the API call.
     * @param resource $stream    A readable stream resource.
     * @param int      $minLength The minimum number of bytes to read.
     * @param int      $maxLength The maximum number of bytes to read.
     *
     * @return null
     */
    public function read(
        Strand $strand,
        $stream,
        int $minLength = PHP_INT_MAX,
        int $maxLength = PHP_INT_MAX
    ) {
        assert($minLength >= 1, 'minimum length must be at least one');
        assert($minLength <= $maxLength, 'minimum length must not exceed maximum length');

        $buffer = '';
        $done = null;
        $done = $this->streamQueue->read(
            $stream,
            function ($stream) use (
                $strand,
                &$minLength,
                &$maxLength,
                &$done,
                &$buffer
            ) {
                $chunk = @\fread(
                    $stream,
                    $maxLength < self::MAX_READ_LENGTH
                        ? $maxLength
                        : self::MAX_READ_LENGTH
                );

                if ($chunk === false) {
                    // @codeCoverageIgnoreStart
                    $done();
                    $error = \error_get_last();
                    $strand->throw(
                        new ErrorException(
                            $error['message'],
                            $error['type'],
                            1, // severity
                            $error['file'],
                            $error['line']
                        ),
                        $strand
                    );
                    // @codeCoverageIgnoreEnd
                } elseif ($chunk === '') {
                    $done();
                    $strand->send($buffer, $strand);
                } else {
                    $buffer .= $chunk;
                    $length = \strlen($chunk);

                    if ($length >= $minLength || $length === $maxLength) {
                        $done();
                        $strand->send($buffer, $strand);
                    } else {
                        $minLength -= $length;
                        $maxLength -= $length;
                    }
                }
            }
        );

        $strand->setTerminator($done);
    }

    /**
     * Write data to a stream resource, blocking the strand until the entire
     * buffer has been written.
     *
     * Data is written until $length bytes have been written, or the entire
     * buffer has been sent, at which point the calling strand is resumed.
     *
     * If the stream is already being written to by another strand, no data is
     * written until the other strand's operation is complete.
     *
     * Similarly, for the duration of the write, calls to {@see Api::select()}
     * will not indicate that the stream is ready for writing.
     *
     * It is assumed that the stream is already configured as non-blocking.
     *
     * @param Strand   $strand The strand executing the API call.
     * @param resource $stream A writable stream resource.
     * @param string   $buffer The data to write to the stream.
     * @param int      $length The maximum number of bytes to write.
     *
     * @return null
     */
    public function write(
        Strand $strand,
        $stream,
        string $buffer,
        int $length = PHP_INT_MAX
    ) {
        $bufferLength = \strlen($buffer);

        if ($bufferLength < $length) {
            $length = $bufferLength;
        }

        $done = null;
        $done = $this->streamQueue->write(
            $stream,
            function ($stream) use (
                $strand,
                &$done,
                &$buffer,
                &$length
            ) {
                $bytes = @\fwrite($stream, $buffer, $length);

                if ($bytes === false) {
                    // @codeCoverageIgnoreStart
                    $done();
                    $error = \error_get_last();
                    $strand->throw(
                        new ErrorException(
                            $error['message'],
                            $error['type'],
                            1, // severity
                            $error['file'],
                            $error['line']
                        ),
                        $strand
                    );
                    // @codeCoverageIgnoreEnd
                } elseif ($bytes === $length) {
                    $done();
                    $strand->send(null, $strand);
                } else {
                    $length -= $bytes;
                    $buffer = \substr($buffer, $bytes);
                }
            }
        );

        $strand->setTerminator($done);
    }

    /**
     * Monitor multiple streams, waiting until one or more becomes "ready" for
     * reading or writing.
     *
     * This operation is directly analogous to {@see stream_select()}, except
     * that it allows other strands to execute while waiting for the streams.
     *
     * A stream is considered ready for reading when a call to {@see fread()}
     * will not block, and likewise ready for writing when {@see fwrite()} will
     * not block.
     *
     * The calling strand is resumed with a 2-tuple containing arrays of the
     * ready streams. This allows the result to be unpacked with {@see list()}.
     *
     * A given stream may be monitored by multiple strands simultaneously, but
     * only one of the strands is resumed when the stream becomes ready. There
     * is no guarantee which strand will be resumed.
     *
     * Any stream that has an in-progress call to {@see Api::read()} or
     * {@see Api::write()} will not be included in the resulting tuple until
     * those operations are complete.
     *
     * If no streams become ready within the specified time, the calling strand
     * is resumed with a {@see TimeoutException}.
     *
     * If no streams are provided, the calling strand is resumed immediately.
     *
     * @param Strand             $strand  The strand executing the API call.
     * @param array<stream>|null $read    Streams monitored until they become "readable" (null = none).
     * @param array<stream>|null $write   Streams monitored until they become "writable" (null = none).
     * @param float|null         $timeout The maximum amount of time to wait, in seconds (null = forever).
     *
     * @return null
     */
    public function select(
        Strand $strand,
        array $read = null,
        array $write = null,
        float $timeout = null
    ) {
        if (empty($read) && empty($write)) {
            $strand->send([[], []], $strand);

            return;
        }

        $context = new class()
        {
            public $strand;
            public $read;
            public $write;
            public $timeout;
            public $timer;
            public $done = [];
        };

        $context->strand = $strand;
        $context->read = $read;
        $context->write = $write;
        $context->timeout = $timeout;

        if ($context->read !== null) {
            foreach ($context->read as $stream) {
                $context->done[] = $this->streamQueue->read(
                    $stream,
                    function ($stream) use ($context) {
                        foreach ($context->done as $done) {
                            $done();
                        }

                        if ($context->timer) {
                            $context->timer->cancel();
                        }

                        $context->strand->send([[$stream], []], $context->strand);
                    }
                );
            }
        }

        if ($context->write !== null) {
            foreach ($context->write as $stream) {
                $context->done[] = $this->streamQueue->write(
                    $stream,
                    function ($stream) use ($context) {
                        foreach ($context->done as $done) {
                            $done();
                        }

                        if ($context->timer) {
                            $context->timer->cancel();
                        }

                        $context->strand->send([[], [$stream]], $context->strand);
                    }
                );
            }
        }

        $context->strand->setTerminator(function () use ($context) {
            foreach ($context->done as $done) {
                $done();
            }

            if ($context->timer) {
                $context->timer->cancel();
            }
        });

        if ($context->timeout !== null) {
            $context->timer = $this->eventLoop->addTimer(
                $context->timeout,
                function () use ($context) {
                    foreach ($context->done as $done) {
                        $done();
                    }

                    $context->strand->throw(
                        new TimeoutException($context->timeout),
                        $context->strand
                    );
                }
            );
        }
    }

    /**
     * Get the event loop.
     *
     * The caller is resumed with the event loop used by this API.
     *
     * @param Strand $strand The strand executing the API call.
     */
    public function eventLoop(Strand $strand)
    {
        $strand->send($this->eventLoop, $strand);
    }

    use ApiTrait;

    const MAX_READ_LENGTH = 32768;

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;

    /**
     * @var StreamQueue The stream queue, used to control sequential access
     *                  to streams.
     */
    private $streamQueue;
}

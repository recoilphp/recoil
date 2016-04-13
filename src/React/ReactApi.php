<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

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
    public function __construct(LoopInterface $eventLoop)
    {
        $this->eventLoop = $eventLoop;

        $this->onRead = function ($stream) {
            $fd = (int) $stream;

            assert(!empty($this->readers[$fd]));
            foreach ($this->readers[$fd] as $context) {
                break;
            }

            $this->detachSelectContext($context);
            $context->strand->resume([[$stream], []]);
        };

        $this->onWrite = function ($stream) {
            $fd = (int) $stream;

            assert(!empty($this->writers[$fd]));
            foreach ($this->writers[$fd] as $context) {
                break;
            }

            $this->detachSelectContext($context);
            $context->strand->resume([[], [$stream]]);
        };
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
                $strand->resume();
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
                    $strand->resume();
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
                    $strand->resume();
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
     * Read data from a stream resource.
     *
     * The calling strand is resumed with a string containing the data read from
     * the stream, or with an empty string if the stream has reached EOF.
     *
     * A length of 0 (zero) may be used to block until the stream is ready for
     * reading without consuming any data.
     *
     * It is assumed that the stream is already configured as non-blocking.
     *
     * @param Strand   $strand The strand executing the API call.
     * @param resource $stream A readable stream resource.
     * @param int      $length The maximum size of the buffer to return, in bytes.
     */
    public function read(Strand $strand, $stream, int $length = 8192)
    {
        $strand->setTerminator(
            function () use ($stream) {
                $this->eventLoop->removeReadStream($stream);
            }
        );

        $this->eventLoop->addReadStream(
            $stream,
            function () use ($strand, $stream, $length) {
                $this->eventLoop->removeReadStream($stream);

                if ($length > 0) {
                    $strand->resume(\fread($stream, $length));
                } else {
                    $strand->resume('');
                }
            }
        );
    }

    /**
     * Write data to a stream resource.
     *
     * The calling strand is resumed with the number of bytes written.
     *
     * An empty buffer, or a length of 0 (zero) may be used to block until the
     * stream is ready for writing without writing any data.
     *
     * It is assumed that the stream is already configured as non-blocking.
     *
     * @param Strand   $strand The strand executing the API call.
     * @param resource $stream A writable stream resource.
     * @param string   $buffer The data to write to the stream.
     * @param int      $length The number of bytes to write from the start of the buffer.
     */
    public function write(
        Strand $strand,
        $stream,
        string $buffer,
        int $length = PHP_INT_MAX
    ) {
        $strand->setTerminator(
            function () use ($stream) {
                $this->eventLoop->removeWriteStream($stream);
            }
        );

        $this->eventLoop->addWriteStream(
            $stream,
            function () use ($strand, $stream, $buffer, $length) {
                $this->eventLoop->removeWriteStream($stream);

                if (!empty($buffer) && $length > 0) {
                    $strand->resume(\fwrite($stream, $buffer, $length));
                } else {
                    $strand->resume(0);
                }
            }
        );
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
            $strand->resume([[], []]);

            return;
        }

        $context = new class()
 {
     public $strand;
     public $read;
     public $write;
     public $timeout;
     public $timer;
 };
        $context->strand = $strand;
        $context->read = $read;
        $context->write = $write;
        $context->timeout = $timeout;

        $id = $context->strand->id();

        $context->strand->setTerminator(function () use ($context) {
            $this->detachSelectContext($context);
        });

        if ($context->read !== null) {
            foreach ($context->read as $stream) {
                $fd = (int) $stream;

                if (empty($this->readers[$fd])) {
                    $this->eventLoop->addReadStream($stream, $this->onRead);
                }

                $this->readers[$fd][$id] = $context;
            }
        }

        if ($context->write !== null) {
            foreach ($context->write as $stream) {
                $fd = (int) $stream;

                if (empty($this->writers[$fd])) {
                    $this->eventLoop->addWriteStream($stream, $this->onWrite);
                }

                $this->writers[$fd][$id] = $context;
            }
        }

        if ($context->timeout !== null) {
            $context->timer = $this->eventLoop->addTimer(
                $context->timeout,
                function () use ($context) {
                    $this->detachSelectContext($context);
                    $context->strand->throw(new TimeoutException($context->timeout));
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
        $strand->resume($this->eventLoop);
    }

    private function detachSelectContext($context)
    {
        $id = $context->strand->id();

        if ($context->read !== null) {
            foreach ($context->read as $stream) {
                $fd = (int) $stream;

                unset($this->readers[$fd][$id]);

                if (empty($this->readers[$fd])) {
                    $this->eventLoop->removeReadStream($stream);
                }
            }
        }

        if ($context->write !== null) {
            foreach ($context->write as $stream) {
                $fd = (int) $stream;

                unset($this->writers[$fd][$id]);

                if (empty($this->writers[$fd])) {
                    $this->eventLoop->removeWriteStream($stream);
                }
            }
        }

        if ($context->timer) {
            $context->timer->cancel();
        }
    }

    use ApiTrait;

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;

    /**
     * @var Closure The callback used for->addReadableStream().
     */
    private $onRead;

    /**
     * @var Closure The callback used for->addWrtiableStream().
     */
    private $onWrite;

    /**
     * @var array<int, array<int, object>> A map of file-descriptor to queue of
     *                 select "contexts" describing the strand that is waiting
     *                 on the stream to become readable.
     */
    private $readers = [];

    /**
     * @var array<int, array<int, object>> A map of file-descriptor to queue of
     *                 select "contexts" describing the strand that is waiting
     *                 on the stream to become writable.
     */
    private $writers = [];
}

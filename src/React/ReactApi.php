<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

use React\EventLoop\LoopInterface;
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
    }

    /**
     * Start a new strand of execution.
     *
     * This operation executes a coroutine in a new strand. The calling strand
     * is resumed with the new {@see Strand} object.
     *
     * The coroutine can be any generator object, a generator function, or any
     * other value supported by {@see Api::dispatch()}.
     *
     * The implementation must delay execution of the new strand until the next
     * 'tick' of the kernel to allow the caller to inspect the strand object
     * before execution begins.
     *
     * @param Strand $strand    The strand executing the API call.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function execute(Strand $strand, $coroutine)
    {
        $strand->resume(
            $strand->kernel()->execute($coroutine)
        );
    }

    /**
     * Create a callback function that starts a new strand of execution.
     *
     * This operation can be used to integrate the kernel with callback-based
     * asynchronous code.
     *
     * The coroutine can be any generator object, a generator function, or any
     * other value supported by {@see Api::dispatch()}.
     *
     * The calling strand is resumed with the callback.
     *
     * @param Strand $strand    The strand executing the API call.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function callback(Strand $strand, $coroutine)
    {
        $kernel = $strand->kernel();

        $strand->resume(
            static function () use ($kernel, $coroutine) {
                $kernel->execute($coroutine);
            }
        );
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
     * It is assumed that the stream is already configured as non-blocking.
     *
     * @param Strand   $strand The strand executing the API call.
     * @param resource $stream A readable stream resource.
     * @param int      $size   The maximum size of the buffer to return, in bytes.
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
                $strand->resume(fread($stream, $length));
            }
        );
    }

    /**
     * Write data to a stream resource.
     *
     * The calling strand is resumed with the number of bytes written.
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
                $strand->resume(fwrite($stream, $buffer, $length));
            }
        );
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

    use ApiTrait;

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;
}

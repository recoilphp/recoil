<?php

declare (strict_types = 1);

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
     * This method executes a coroutine in a new strand. The calling strand is
     * resumed with the new {@see Strand} object.
     *
     * The coroutine can be a generator object, or a generator function.
     *
     * The implementation must delay execution of the strand until the next
     * 'tick' of the kernel to allow the user to inspect the strand object
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
     * This method can be used to integrate the kernel with callback-based
     * asynchronous code.
     *
     * The coroutine can be a generator object, or a generator function.
     *
     * The caller is resumed with the callback.
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
     * Allow other strands to execute then resume the strand.
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
     * Resume execution of the strand after a specified interval.
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
     * Execute a coroutine on its own strand that is terminated after a timeout.
     *
     * If the coroutine does not complete within the specific time its strand is
     * terminated and the calling strand is resumed with a {@see TimeoutException}.
     * Otherwise, the calling strand is resumed with the value or exception
     * produced by the coroutine.
     *
     * @param Strand $strand    The strand executing the API call.
     * @param float  $seconds   The interval to allow for execution.
     * @param mixed  $coroutine The coroutine to execute.
     */
    public function timeout(Strand $strand, float $seconds, $coroutine)
    {
        $substrand = $strand->kernel()->execute($coroutine);

        $timer = $this->eventLoop->addTimer(
            $seconds,
            static function () use ($substrand) {
                $substrand->terminate();
            }
        );

        $substrand->setTerminator([$timer, 'cancel']);
        $substrand->awaitable()->await($strand, $this);
    }

    /**
     * Read data from a stream.
     *
     * The calling strand is resumed when a string containing the data read from
     * the stream, or with an empty string if the stream is closed while waiting
     * for data.
     *
     * @param Strand   $strand The strand executing the API call.
     * @param resource $stream A readable stream.
     * @param int      $size   The maximum size of the buffer to return, in bytes.
     */
    public function read(Strand $strand, $stream, int $size = 8192)
    {
        if (!is_resource($stream) || feof($stream)) {
            $strand->throw(new Exception);

            return;
        }

        // stream_set_blocking($stream, false);
        // stream_set_read_buffer($stream, 0);

        $this->eventLoop->addReadStream(
            $stream,
            function () use ($strand, $stream, $size) {
                $this->eventLoop->removeReadStream($stream);
                $buffer = fread($stream, $size);

                if ($buffer === false) {
                    $info = stream_get_meta_data($stream);
                    $strand->throw(new Exception()); // @todo
                } else {
                    $strand->resume($buffer);
                }
            }
        );
    }

    /**
     * Write data to a stream.
     *
     * @param Strand   $strand The strand executing the API call.
     * @param resource $stream A writable stream.
     * @param string   $buffer The data to write to the stream.
     */
    public function write(Strand $strand, $stream, string $buffer)
    {
        if (!is_resource($stream) || feof($stream)) {
            $strand->throw(new Exception()); // @todo
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

    use ApiTrait;

    /**
     * @var LoopInterface The event loop.
     */
    private $eventLoop;
}

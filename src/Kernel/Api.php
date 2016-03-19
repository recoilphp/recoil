<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Recoil\Exception\CompositeException;
use Recoil\Exception\TimeoutException;

interface Api
{
    /**
     * Dispatch an API call based on the key and value yielded from a coroutine.
     *
     * The implementation should not attribute any special behaviour to integer
     * keys, as PHP's generator implementation implicitly yields integer keys
     * when a value is yielded without specifying a key.
     *
     * @param Strand $strand The strand executing the API call.
     * @param mixed  $key    The yielded key.
     * @param mixed  $value  The yielded value.
     *
     * @return null
     */
    public function dispatch(Strand $strand, $key, $value);

    /**
     * Invoke a non-standard API operation.
     *
     * The first element in $arguments must be the calling strand.
     *
     * @return null
     */
    public function __call(string $name, array $arguments);

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
     *
     * @return null
     */
    public function execute(Strand $strand, $coroutine);

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
     *
     * @return null
     */
    public function callback(Strand $strand, $coroutine);

    /**
     * Allow other strands to execute before resuming the calling strand.
     *
     * @param Strand $strand The strand executing the API call.
     *
     * @return null
     */
    public function cooperate(Strand $strand);

    /**
     * Suspend the calling strand for a fixed interval.
     *
     * @param Strand $strand  The strand executing the API call.
     * @param float  $seconds The interval to wait.
     *
     * @return null
     */
    public function sleep(Strand $strand, float $seconds);

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
     *
     * @return null
     */
    public function timeout(Strand $strand, float $seconds, $coroutine);

    /**
     * Suspend execution of the calling strand until it is manually resumed or
     * terminated.
     *
     * This operation is typically used to integrate coroutines with other forms
     * of asynchronous code.
     *
     * @param Strand        $strand The strand executing the API call.
     * @param callable|null $fn     A function invoked with the strand after it is suspended.
     *
     * @return null
     */
    public function suspend(Strand $strand, callable $fn = null);

    /**
     * Terminate the calling strand.
     *
     * @param Strand $strand The strand executing the API call.
     *
     * @return null
     */
    public function terminate(Strand $strand);

    /**
     * Execute multiple coroutines on new strands and wait for them all to exit.
     *
     * If any one of the strands fails, all remaining strands are terminated and
     * the calling strand is resumed with the underlying exception.
     *
     * Otherwise, the calling strand is resumed with an associative array
     * containing the return values of each coroutine.
     *
     * The array keys correspond to the order that the coroutines are passed to
     * the operation. The order of the elements in the array matches the order
     * in which the strands exited. This allows predictable unpacking of the
     * array with {@see list()} (which uses the keys), while still being able to
     * tell the exit order if necessary.
     *
     * @param Strand $strand         The strand executing the API call.
     * @param mixed  $coroutines,... The coroutines to execute.
     *
     * @return null
     */
    public function all(Strand $strand, ...$coroutines);

    /**
     * Execute multiple coroutines on new strands and wait for any one of them
     * to succeed.
     *
     * If any one of the strands succeeds, all remaining strands are terminated
     * and the calling strand is resumed with the return value of the coroutine.
     *
     * If all of the strands fail, the calling strand is resumed with a
     * {@see CompositeException}.
     *
     * @param Strand $strand         The strand executing the API call.
     * @param mixed  $coroutines,... The coroutines to execute.
     *
     * @return null
     */
    public function any(Strand $strand, ...$coroutines);

    /**
     * Execute multiple coroutines on new strands and wait for a subset of them
     * to succeed.
     *
     * Once the specified number of strands have succeeded, all remaining
     * strands are terminated and the calling strand is resumed with an
     * associative array containing the return values of each successful
     * coroutine.
     *
     * The array keys correspond to the order that the coroutines are passed to
     * the operation. The order of the elements in the array matches the order
     * in which the strands exited.
     *
     * Unlike {@see Api::all()}, {@see list()} can not be used to unpack the
     * result directly, as the caller can not predict which of the strands will
     * succeed.
     *
     * If enough strands fail, such that is no longer possible for the required
     * number of strands to succeed, all remaining strands are terminated and
     * the calling strand is resumed with a {@see CompositeException}.
     *
     * The specified count must be between 1 and the number of provided
     * coroutines, inclusive.
     *
     * @param Strand $strand         The strand executing the API call.
     * @param int    $count          The required number of successful strands.
     * @param mixed  $coroutines,... The coroutines to execute.
     *
     * @return null
     */
    public function some(Strand $strand, int $count, ...$coroutines);

    /**
     * Execute multiple coroutines on new strands and wait for any one of them
     * to exit.
     *
     * If any one of the strands exits, all remaining strands are terminated
     * and the calling strand is resumed with the return value or exception
     * produced by the coroutine.
     *
     * @param Strand $strand         The strand executing the API call.
     * @param mixed  $coroutines,... The coroutines to execute.
     *
     * @return null
     */
    public function first(Strand $strand, ...$coroutines);

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
     *
     * @return null
     */
    public function read(Strand $strand, $stream, int $length = 8192);

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
     *
     * @return null
     */
    public function write(
        Strand $strand,
        $stream,
        string $buffer,
        int $length = PHP_INT_MAX
    );
}

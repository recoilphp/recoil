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
     * @return Generator|null
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
     * @return Generator|null
     */
    public function execute(Strand $strand, $coroutine);

    /**
     * Create a callback function that starts a new strand of execution.
     *
     * This operation can be used to integrate the kernel with callback-based
     * asynchronous code.
     *
     * Any arguments passed to the callback function are forwarded to the
     * coroutine.
     *
     * The calling strand is resumed with the callback.
     *
     * @param Strand   $strand    The strand executing the API call.
     * @param callable $coroutine The coroutine to execute.
     *
     * @return Generator|null
     */
    public function callback(Strand $strand, callable $coroutine);

    /**
     * Allow other strands to execute before resuming the calling strand.
     *
     * @param Strand $strand The strand executing the API call.
     *
     * @return Generator|null
     */
    public function cooperate(Strand $strand);

    /**
     * Suspend the calling strand for a fixed interval.
     *
     * @param Strand $strand  The strand executing the API call.
     * @param float  $seconds The interval to wait.
     *
     * @return Generator|null
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
     * @return Generator|null
     */
    public function timeout(Strand $strand, float $seconds, $coroutine);

    /**
     * Get the {@see Strand} object that represents the calling strand.
     *
     * @param Strand $strand The strand executing the API call.
     *
     * @return Generator|null
     */
    public function strand(Strand $strand);

    /**
     * Suspend execution of the calling strand until it is manually resumed or
     * terminated.
     *
     * This operation is typically used to integrate coroutines with other forms
     * of asynchronous code.
     *
     * @param Strand        $strand      The strand executing the API call.
     * @param callable|null $suspendFn   A function invoked with the strand after it is suspended.
     * @param callable|null $terminateFn A function invoked if the strand is terminated while suspended.
     *
     * @return Generator|null
     */
    public function suspend(
        Strand $strand,
        callable $suspendFn = null,
        callable $terminateFn = null
    );

    /**
     * Terminate the calling strand.
     *
     * @param Strand $strand The strand executing the API call.
     *
     * @return Generator|null
     */
    public function terminate(Strand $strand);

    /**
     * Create a bi-directional link between two strands.
     *
     * If either strand exits, the other is terminated.
     *
     * @param Strand      $strand  The strand executing the API call.
     * @param Strand      $strandA The first strand to link.
     * @param Strand|null $strandB The first strand to link (null = calling strand).
     *
     * @return Generator|null
     */
    public function link(
        Strand $strand,
        Strand $strandA,
        Strand $strandB = null
    );

    /**
     * Break a previously established bi-directional link between strands.
     *
     * @param Strand      $strand  The strand executing the API call.
     * @param Strand      $strandA The first strand to unlink.
     * @param Strand|null $strandB The first strand to unlink (null = calling strand).
     *
     * @return Generator|null
     */
    public function unlink(
        Strand $strand,
        Strand $strandA,
        Strand $strandB = null
    );

    /**
     * Take ownership of a strand, wait for it to exit and propagate its result
     * to the calling strand.
     *
     * If the calling strand is terminated, the substrand is also terminated.
     *
     * The calling strand is resumed with the return value or exception of the
     * substrand upon exit.
     *
     * Adopting a strand prevents the kernel's exception handler from being
     * invoked. It is the calling strand's responsibility to handle the
     * exception.
     *
     * @see Kernel::setExceptionHandler()
     *
     * @param Strand $strand    The strand executing the API call.
     * @param Strand $substrand The strand to monitor.
     *
     * @return Generator|null
     */
    public function adopt(Strand $strand, Strand $substrand);

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
     * @return Generator|null
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
     * @return Generator|null
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
     * @return Generator|null
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
     * @return Generator|null
     */
    public function first(Strand $strand, ...$coroutines);

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
     * @return Generator|null
     */
    public function read(
        Strand $strand,
        $stream,
        int $minLength = PHP_INT_MAX,
        int $maxLength = PHP_INT_MAX
    );

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
     * @return Generator|null
     */
    public function write(
        Strand $strand,
        $stream,
        string $buffer,
        int $length = PHP_INT_MAX
    );

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
     * @return Generator|null
     */
    public function select(
        Strand $strand,
        array $read = null,
        array $write = null,
        float $timeout = null
    );
}

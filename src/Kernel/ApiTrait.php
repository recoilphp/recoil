<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use BadMethodCallException;
use Icecave\Repr\Repr;
use InvalidArgumentException;
use Recoil\Exception\RejectedException;
use Throwable;
use UnexpectedValueException;

/**
 * A partial implementation of {@see Api}.
 */
trait ApiTrait
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
     */
    public function dispatch(Strand $strand, $key, $value)
    {
        if (null === $value) {
            $this->cooperate($strand);
        } elseif (\is_integer($value) || \is_float($value)) {
            $this->sleep($strand, $value);
        } elseif (\is_array($value)) {
            $this->all($strand, ...$value);
        } elseif (\is_resource($value)) {
            if (\is_string($key)) {
                $this->write($strand, $value, $key);
            } else {
                $this->read($strand, $value, 1);
            }
        } elseif (\method_exists($value, 'then')) {
            $value->then(
                static function ($result) use ($strand) {
                    $strand->send($result);
                },
                static function ($reason) use ($strand) {
                    if ($reason instanceof Throwable) {
                        $strand->throw($reason);
                    } else {
                        $strand->throw(new RejectedException($reason));
                    }
                }
            );

            if (\method_exists($value, 'cancel')) {
                $strand->setTerminator(function () use ($value) {
                    $value->cancel();
                });
            }
        } else {
            $strand->throw(
                new UnexpectedValueException(
                    'The yielded pair ('
                    . Repr::repr($key)
                    . ', '
                    . Repr::repr($value)
                    . ') does not describe any known operation.'
                ),
                $strand
            );
        }
    }

    /**
     * Invoke a non-standard API operation.
     *
     * The first element in $arguments must be the calling strand.
     */
    public function __call(string $name, array $arguments)
    {
        (function (string $name, Strand $strand) {
            $strand->throw(
                new BadMethodCallException(
                    'The API does not implement an operation named "' . $name . '".'
                ),
                $strand
            );
        })($name, ...$arguments);
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
        $strand->send(
            $strand->kernel()->execute($coroutine),
            $strand
        );
    }

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
     */
    public function callback(Strand $strand, callable $coroutine)
    {
        $kernel = $strand->kernel();

        $strand->send(
            static function (...$arguments) use ($kernel, $coroutine) {
                $kernel->execute($coroutine(...$arguments));
            },
            $strand
        );
    }

    /**
     * Get the {@see Strand} object that represents the calling strand.
     *
     * @param Strand $strand The strand executing the API call.
     *
     * @return null
     */
    public function strand(Strand $strand)
    {
        $strand->send($strand, $strand);
    }

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
     */
    public function suspend(
        Strand $strand,
        callable $suspendFn = null,
        callable $terminateFn = null
    ) {
        if ($terminateFn) {
            $strand->setTerminator($terminateFn);
        }
        if ($suspendFn) {
            $suspendFn($strand);
        }
    }

    /**
     * Terminate the calling strand.
     *
     * @param Strand $strand The strand executing the API call.
     */
    public function terminate(Strand $strand)
    {
        $strand->terminate();
    }

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
    ) {
        if ($strandB === null) {
            $strandB = $strand;
        }

        $strandA->link($strandB);
        $strandB->link($strandA);

        $strand->send(null, $strand);
    }

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
    ) {
        if ($strandB === null) {
            $strandB = $strand;
        }

        $strandA->unlink($strandB);
        $strandB->unlink($strandA);

        $strand->send(null, $strand);
    }

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
    public function adopt(Strand $strand, Strand $substrand)
    {
        $strand->setTerminator(function () use ($substrand) {
            $substrand->clearPrimaryListener();
            $substrand->terminate();
        });

        $substrand->setPrimaryListener($strand);
    }

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
     */
    public function all(Strand $strand, ...$coroutines)
    {
        $kernel = $strand->kernel();
        $substrands = [];

        foreach ($coroutines as $coroutine) {
            $substrands[] = $kernel->execute($coroutine);
        }

        (new StrandWaitAll(...$substrands))->await($strand, $this);
    }

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
     */
    public function any(Strand $strand, ...$coroutines)
    {
        $kernel = $strand->kernel();
        $substrands = [];

        foreach ($coroutines as $coroutine) {
            $substrands[] = $kernel->execute($coroutine);
        }

        (new StrandWaitAny(...$substrands))->await($strand, $this);
    }

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
     */
    public function some(Strand $strand, int $count, ...$coroutines)
    {
        $max = \count($coroutines);

        if ($count < 1 || $count > $max) {
            $strand->throw(
                new InvalidArgumentException(
                    'Can not wait for '
                    . $count
                    . ' coroutines, count must be between 1 and '
                    . $max
                    . ', inclusive.'
                ),
                $strand
            );

            return;
        }

        $kernel = $strand->kernel();
        $substrands = [];

        foreach ($coroutines as $coroutine) {
            $substrands[] = $kernel->execute($coroutine);
        }

        (new StrandWaitSome($count, ...$substrands))->await($strand, $this);
    }

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
     */
    public function first(Strand $strand, ...$coroutines)
    {
        $kernel = $strand->kernel();
        $substrands = [];

        foreach ($coroutines as $coroutine) {
            $substrands[] = $kernel->execute($coroutine);
        }

        (new StrandWaitFirst(...$substrands))->await($strand, $this);
    }

    /**
     * Allow other strands to execute before resuming the calling strand.
     *
     * @param Strand $strand The strand executing the API call.
     *
     * @return null
     */
    abstract public function cooperate(Strand $strand);

    /**
     * Suspend the calling strand for a fixed interval.
     *
     * @param Strand $strand  The strand executing the API call.
     * @param float  $seconds The interval to wait.
     *
     * @return null
     */
    abstract public function sleep(Strand $strand, float $seconds);

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
    abstract public function read(
        Strand $strand,
        $stream,
        int $minLength = 1,
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
     * @return null
     */
    abstract public function write(
        Strand $strand,
        $stream,
        string $buffer,
        int $length = PHP_INT_MAX
    );
}

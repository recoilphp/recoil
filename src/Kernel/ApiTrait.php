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
                $this->read($strand, $value);
            }
        } elseif (\method_exists($value, 'then')) {
            $value->then(
                static function ($result) use ($strand) {
                    $strand->resume($result);
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
                $strand->setTerminator([$value, 'cancel']);
            }
        } else {
            $strand->throw(
                new UnexpectedValueException(
                    'The yielded pair ('
                    . Repr::repr($key)
                    . ', '
                    . Repr::repr($value)
                    . ') does not describe any known operation.'
                )
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
                )
            );
        })($name, ...$arguments);
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
        $strand->resume($strand);
    }

    /**
     * Suspend execution of the calling strand until it is manually resumed or
     * terminated.
     *
     * This operation is typically used to integrate coroutines with other forms
     * of asynchronous code.
     *
     * @param Strand        $strand The strand executing the API call.
     * @param callable|null $fn     A function invoked with the strand after it is suspended.
     */
    public function suspend(Strand $strand, callable $fn = null)
    {
        if ($fn) {
            $fn($strand);
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
                )
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
     *
     * @return null
     */
    abstract public function read(Strand $strand, $stream, int $length = 8192);

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

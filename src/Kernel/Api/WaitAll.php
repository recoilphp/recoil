<?php
namespace Recoil\Kernel\Api;

use Exception;
use Recoil\Coroutine\CoroutineInterface;
use Recoil\Coroutine\CoroutineTrait;
use Recoil\Kernel\Exception\StrandTerminatedException;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * Internal implementation of KernelApiInterface::all().
 *
 * @internal
 */
class WaitAll implements CoroutineInterface
{
    use CoroutineTrait;

    public function __construct(array $coroutines)
    {
        $this->coroutines = $coroutines;
        $this->substrands = [];
        $this->values = [];
    }

    /**
     * Start the coroutine.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function call(StrandInterface $strand)
    {
        $this->strand = $strand;
        $this->strand->suspend();

        foreach ($this->coroutines as $index => $coroutine) {
            $this->values[$index] = null;

            $this->substrands[$index] = $substrand = $strand
                ->kernel()
                ->execute($coroutine);

            $substrand->on(
                'success',
                function ($strand, $value) use ($index) {
                    $this->values[$index] = $value;
                }
            );

            $substrand->on(
                'error',
                function ($strand, $exception, $preventDefault) {
                    $this->exception = $exception;
                    $preventDefault();
                }
            );

            $substrand->on(
                'terminate',
                function () {
                    $this->exception = new StrandTerminatedException();
                }
            );

            $substrand->on(
                'exit',
                function () use ($index) {
                    unset($this->substrands[$index]);

                    if (!$this->strand) {
                        return;
                    } elseif ($this->exception) {
                        $this->strand->resumeWithException($this->exception);
                        $this->strand = null;
                    } elseif (!$this->substrands) {
                        $this->strand->resumeWithValue($this->values);
                        $this->strand = null;
                    }
                }
            );
        }
    }

    /**
     * Finalize the coroutine.
     *
     * This method is invoked after the coroutine is popped from the call stack.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function finalize(StrandInterface $strand)
    {
        $this->strand = null;

        foreach ($this->substrands as $substrand) {
            $substrand->terminate();
        }
    }

    private $coroutines;
    private $strand;
    private $substrands;
    private $values;
    private $exception;
}

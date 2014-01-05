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
        $this->returnValues = [];
    }

    /**
     * Start the coroutine.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function call(StrandInterface $strand)
    {
        foreach ($this->coroutines as $index => $coroutine) {
            $this->returnValues[$index] = null;
            $this->waitStrands[$index] = $s = $strand
                ->kernel()
                ->execute($coroutine);

            $s->on(
                'success',
                function ($s, $value) use ($index) {
                    $this->returnValues[$index] = $value;
                    unset($this->waitStrands[$index]);
                }
            );

            $s->on(
                'error',
                function ($s, $exception, $preventDefault) use ($index) {
                    if (!$this->exception) {
                        $this->exception = $exception;
                    }
                    $preventDefault();
                    unset($this->waitStrands[$index]);
                }
            );

            $s->on(
                'terminate',
                function ($s) use ($index) {
                    if (!$this->exception) {
                        $this->exception = new StrandTerminatedException;
                    }
                    unset($this->waitStrands[$index]);
                }
            );
        }

        $strand->call(
            new Select($this->waitStrands)
        );
    }

    /**
     * Resume execution of a suspended coroutine by passing it a value.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     * @param mixed           $value  The value to send to the coroutine.
     */
    public function resumeWithValue(StrandInterface $strand, $value)
    {
        if ($this->exception) {
            $strand->throwException($this->exception);
        } elseif ($this->waitStrands) {
            $strand->call(
                new Select($this->waitStrands)
            );
        } else {
            $strand->returnValue($this->returnValues);
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
        foreach ($this->waitStrands as $s) {
            $s->terminate();
        }
    }

    private $coroutines;
    private $waitStrands;
    private $returnValues;
    private $exception;
}

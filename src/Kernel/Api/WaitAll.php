<?php
namespace Recoil\Kernel\Api;

use Exception;
use Recoil\Coroutine\AbstractCoroutine;
use Recoil\Kernel\Exception\StrandTerminatedException;
use Recoil\Kernel\Strand\StrandInterface;

/**
 * Internal implementation of KernelApiInterface::all().
 *
 * @internal
 */
class WaitAll extends AbstractCoroutine
{
    public function __construct(array $coroutines)
    {
        $this->coroutines = $coroutines;
        $this->returnValues = [];

        parent::__construct();
    }

    /**
     * Invoked when tick() is called for the first time.
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
     * Invoked when tick() is called after sendOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     * @param mixed           $value  The value passed to sendOnNextTick().
     */
    public function resumeWithValue(StrandInterface $strand, $value)
    {
        if ($this->exception) {
            foreach ($this->waitStrands as $s) {
                $s->terminate();
            }
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
     * Invoked when tick() is called after throwOnNextTick().
     *
     * @codeCoverageIgnore
     *
     * @param StrandInterface $strand    The strand that is executing the coroutine.
     * @param Exception       $exception The exception passed to throwOnNextTick().
     */
    public function resumeWithException(StrandInterface $strand, Exception $exception)
    {
        throw new Exception('Not supported.');
    }

    /**
     * Invoked when tick() is called after terminateOnNextTick().
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function terminate(StrandInterface $strand)
    {
        foreach ($this->waitStrands as $s) {
            $s->terminate();
        }

        $strand->pop();
        $strand->terminate();
    }

    private $coroutines;
    private $waitStrands;
    private $returnValues;
    private $exception;
}

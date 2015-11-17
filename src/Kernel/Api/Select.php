<?php

namespace Recoil\Kernel\Api;

use Recoil\Coroutine\CoroutineInterface;
use Recoil\Coroutine\CoroutineTrait;
use Recoil\Kernel\Strand\StrandInterface;
use SplObjectStorage;

/**
 * Internal implementation of KernelApiInterface::select().
 *
 * @internal
 */
class Select implements CoroutineInterface
{
    use CoroutineTrait;

    public function __construct(array $strands)
    {
        $this->substrands = new SplObjectStorage();
        $this->exited     = [];

        foreach ($strands as $index => $strand) {
            if ($strand->hasExited()) {
                $this->exited[$index] = $strand;
            } else {
                $this->substrands->attach($strand, $index);
            }
        }
    }

    /**
     * Start the coroutine.
     *
     * @param StrandInterface $strand The strand that is executing the coroutine.
     */
    public function call(StrandInterface $strand)
    {
        // If some of the strands have exited already, resume immediately ...
        if ($this->exited) {
            $strand->resumeWithValue($this->exited);

            return;
        }

        // Otherwise, suspend the current strand until at least one strand exits ...
        $this->strand = $strand;
        $this->strand->suspend();

        foreach ($this->substrands as $strand) {
            $strand->on(
                'exit',
                [$this, 'onStrandExit']
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
        foreach ($this->substrands as $strand) {
            $strand->removeListener(
                'exit',
                [$this, 'onStrandExit']
            );
        }
    }

    public function onStrandExit(StrandInterface $strand)
    {
        $index = $this->substrands[$strand];

        $this->substrands->detach($strand);

        $this->exited[$index] = $strand;

        $this->strand->resumeWithValue($this->exited);
    }

    private $strand;
    private $substrands;
    private $exited;
}

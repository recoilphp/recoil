<?php

namespace Recoil\Kernel\Api;

use Recoil\Coroutine\Coroutine;
use Recoil\Coroutine\CoroutineTrait;
use Recoil\Kernel\Strand\Strand;
use SplObjectStorage;

/**
 * Internal implementation of KernelApi::select().
 *
 * @access private
 */
class Select implements Coroutine
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
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function call(Strand $strand)
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
     * @param Strand $strand The strand that is executing the coroutine.
     */
    public function finalize(Strand $strand)
    {
        foreach ($this->substrands as $strand) {
            $strand->removeListener(
                'exit',
                [$this, 'onStrandExit']
            );
        }
    }

    public function onStrandExit(Strand $strand)
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

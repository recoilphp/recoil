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
        $this->pendingStrands = [];
        $this->exitedStrands = [];

        foreach ($strands as $strand) {
            if ($strand->hasExited()) {
                $this->exitedStrands[] = $strand;
            } else {
                $this->pendingStrands[] = $strand;
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
        if ($this->exitedStrands) {
            $strand->resumeWithValue($this->exitedStrands);

            return;
        }

        // Otherwise, suspend the current strand until at least one strand exits ...
        $this->callingStrand = $strand;
        $this->callingStrand->suspend();

        foreach ($this->pendingStrands as $strand) {
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
        foreach ($this->pendingStrands as $strand) {
            $strand->removeListener(
                'exit',
                [$this, 'onStrandExit']
            );
        }
    }

    public function onStrandExit(StrandInterface $strand)
    {
        $this->exitedStrands[] = $strand;

        $this->callingStrand->resumeWithValue($this->exitedStrands);
    }

    private $callingStrand;
    private $pendingStrands;
    private $exitedStrands;
}

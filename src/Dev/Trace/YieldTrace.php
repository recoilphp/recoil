<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Trace;

/**
 * Provides information about a yield statement inside a coroutine.
 */
final class YieldTrace implements Trace
{
    /**
     * @var int The line number.
     */
    public $line;

    /**
     * @param int   $line  The line number of the yielded value.
     * @param mixed $value The yielded value.
     */
    public function __construct(int $line, $value = null)
    {
        $this->line = $line;
        $this->value = $value;
    }

    /**
     * Get the yielded value.
     *
     * This method must only be called once. The value is removed from the trace
     * after it is fetched to prevent keeping a reference to it as this may
     * cause the presence or absence of traces to affect the program behaviour.
     *
     * @return mixed
     */
    public function value()
    {
        try {
            return $this->value;
        } finally {
            $this->value = null;
        }
    }

    private $value;
}
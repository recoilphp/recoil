<?php
namespace Icecave\Recoil\Kernel\Strand;

use Exception;

interface StrandResultInterface
{
    /**
     * Get the value produced by the strand.
     *
     * @return mixed                     The value produced by the strand.
     * @throws Exception                 if the strand produced an exception.
     * @throws StrandTerminatedException if the strand was terminated.
     */
    public function get();

    /**
     * Get the exception produced by the strand.
     *
     * @return Exception|null The exception produced by the strand, or null if it produced a value.
     */
    public function getException();

    /**
     * Check if the strand produced a value.
     *
     * @return boolean True if the strand produced a value.
     */
    public function isValue();

    /**
     * Check if the strand produced an exception.
     *
     * @return boolean True if the strand produced an exception.
     */
    public function isException();

    /**
     * Check if the strand was terminated.
     *
     * @return boolean True if the strand was terminated.
     */
    public function isTerminated();
}

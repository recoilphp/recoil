<?php

declare (strict_types = 1);

namespace Recoil\Kernel;

use Recoil\CoroutineTestTrait;

/**
 * A suite of functional tests for the the coroutine kernel.
 */
trait KernelTestTrait
{
    public function recoilTestReturn()
    {
        $this->expectResult('<ok>');

        return '<ok>';
        yield;
    }

    use CoroutineTestTrait;
}

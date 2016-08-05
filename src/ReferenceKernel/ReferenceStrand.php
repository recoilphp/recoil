<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

use Recoil\Awaitable;
use Recoil\Kernel\StrandTrait;
use Recoil\Kernel\SystemStrand;

final class ReferenceStrand implements SystemStrand, Awaitable
{
    use StrandTrait;
}

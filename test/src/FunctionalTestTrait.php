<?php

declare (strict_types = 1);

namespace Recoil;

use Recoil\Kernel\Api;
use Recoil\Kernel\Strand;

/**
 * A suite of functional tests that verify the behavior of a given
 * kernel / api / strand implementation.
 */
trait FunctionalTestTrait
{
    use AsyncTestTrait;
    use FunctionalInvokeTestTrait;
    use FunctionalApiTestTrait;
}

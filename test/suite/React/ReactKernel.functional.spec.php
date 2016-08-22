<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\React;

describe('ReactPHP Integration', function () {
    \Recoil\importFunctionalTests(
        function () {
            return ReactKernel::create();
        }
    );
});

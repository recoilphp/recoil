<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

\Recoil\importFunctionalTests(
    'ReactPHP',
    function () {
        return new ReactKernel();
    }
);

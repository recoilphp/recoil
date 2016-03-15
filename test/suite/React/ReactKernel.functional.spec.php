<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\React;

\Recoil\defineFunctionalSpec(
    'ReactPHP',
    function () {
        return new ReactKernel();
    }
);

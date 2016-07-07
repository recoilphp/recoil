<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

rit('resumes execution after the specified number of seconds', function () {
    $time = microtime(true);
    yield Recoil::sleep(0.02);
    $diff = microtime(true) - $time;

    expect($diff)->to->be->within(0.01, 1.03);
});

rit('can be invoked by yielding a number', function () {
    $time = microtime(true);
    yield 0.02;
    $diff = microtime(true) - $time;

    expect($diff)->to->be->within(0.01, 0.03);
});

xit('does not delay the kernel when a sleeping strand is terminated', function () {

});

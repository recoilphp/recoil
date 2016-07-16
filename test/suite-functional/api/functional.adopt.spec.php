<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;

rit('resumes the calling strand on success', function () {
    $substrand = yield Recoil::execute(function () {
        return '<result>';
        yield;
    });

    expect(yield Recoil::adopt($substrand))->to->equal('<result>');
});

rit('resumes the calling strand on failure', function () {
    $exception = new Exception('<exception>');
    $substrand = yield Recoil::execute(function () use ($exception) {
        throw $exception;
        yield;
    });

    try {
        yield Recoil::adopt($substrand);
        assert(false, 'expected exception was not thrown');
    } catch (Exception $e) {
        assert($e === $exception);
    }
});

rit('terminates the substrand if the calling strand is terminated', function () {
    $substrand = yield Recoil::execute(function () {
        yield;
        assert(false, 'strand was not terminated');
    });

    $strand = yield Recoil::execute(function () use ($substrand) {
        yield Recoil::adopt($substrand);
    });

    yield;

    $strand->terminate();
});

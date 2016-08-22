<?php

declare(strict_types=1); // @codeCoverageIgnore

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
        expect(false)->to->be->ok('expected exception was not thrown');
    } catch (Exception $e) {
        expect($e === $exception)->to->be->true;
    }
});

rit('terminates the substrand if the calling strand is terminated', function () {
    $substrand = yield Recoil::execute(function () {
        yield;
        expect(false)->to->be->ok('strand was not terminated');
    });

    $strand = yield Recoil::execute(function () use ($substrand) {
        yield Recoil::adopt($substrand);
    });

    yield;

    $strand->terminate();
});

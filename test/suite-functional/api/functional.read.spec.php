<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

beforeEach(function () {
    $this->content = file_get_contents(__FILE__);
    $this->stream = fopen(__FILE__, 'r');
    stream_set_blocking($this->stream, false);
});

afterEach(function () {
    fclose($this->stream);
});

rit('reads the entire stream by default', function () {
    expect(yield Recoil::read($this->stream))->to->equal($this->content);
});

rit('can be invoked by yielding a stream', function () {
    $buffer = '';

    do {
        $buf = yield $this->stream;
        $buffer .= $buf;
    } while ($buf !== '');

    expect($buffer)->to->equal($this->content);
});

rit('only reads up to the specified maximum length', function () {
    expect(yield Recoil::read($this->stream, 1, 16))->to->equal(substr($this->content,  0, 16));
    expect(yield Recoil::read($this->stream, 1, 16))->to->equal(substr($this->content, 16, 16));
});

rit('returns an empty string at eof', function () {
    yield Recoil::read($this->stream);
    expect(yield Recoil::read($this->stream))->to->equal('');
});

rit('stops waiting for the stream when the strand is terminated', function () {
    $temp = tempnam(sys_get_temp_dir(), 'recoil-test-fifo-');
    unlink($temp);
    posix_mkfifo($temp, 0644);
    $stream = fopen($temp, 'w+'); // must be w+ (read/write) to prevent blocking
    stream_set_blocking($stream, false);

    $strand = yield Recoil::execute(function () use ($stream) {
        yield Recoil::read($stream);
        expect(false)->to->be->ok('strand was not terminated');
    });

    yield;

    $strand->terminate();

    @unlink($temp);
});

rit('synchronises access across multiple strands', function () {
    $temp = tempnam(sys_get_temp_dir(), 'recoil-test-fifo-');
    unlink($temp);
    posix_mkfifo($temp, 0644);
    $stream = fopen($temp, 'w+'); // must be w+ (read/write) to prevent blocking
    stream_set_blocking($stream, false);

    $buffer1 = '';
    $buffer2 = '';

    yield Recoil::execute(function () use ($stream, &$buffer1) {
        $buffer1 = yield Recoil::read($stream, 4);
    });

    yield Recoil::execute(function () use ($stream, &$buffer2) {
        $buffer2 = yield Recoil::read($stream, 4);
    });

    // write two bytes of data to the stream at a time and yield to allow
    // the reading strands to execute. in order to verify that access is truly
    // synchronised, we can not allow either strand to read their minimum buffer
    // length (8)
    fwrite($stream, '12');
    yield;
    fwrite($stream, '34');
    yield;
    fwrite($stream, '12');
    yield;
    fwrite($stream, '34');
    yield;

    expect($buffer1)->to->equal('1234');
    expect($buffer2)->to->equal('1234');
});

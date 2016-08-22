<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

beforeEach(function () {
    $this->stream = tmpfile();
    stream_set_blocking($this->stream, false);
});

afterEach(function () {
    fclose($this->stream);
});

rit('writes to the stream', function () {
    yield Recoil::write($this->stream, '<buffer>');
    fseek($this->stream, 0);
    expect(stream_get_contents($this->stream))->to->equal('<buffer>');
});

rit('can be invoked by yielding a stream and buffer', function () {
    yield '<buffer>' => $this->stream;
    fseek($this->stream, 0);
    expect(stream_get_contents($this->stream))->to->equal('<buffer>');
});

rit('only writes up to the specified maximum length', function () {
    yield Recoil::write($this->stream, '<buffer>', 4);
    fseek($this->stream, 0);
    expect(stream_get_contents($this->stream))->to->equal('<buf');
});

rit('can be called with a length of zero', function () {
    yield Recoil::write($this->stream, '<buffer>', 0);
    fseek($this->stream, 0);
    expect(stream_get_contents($this->stream))->to->equal('');
});

rit('can be called with an empty buffer', function () {
    yield Recoil::write($this->stream, '');
    fseek($this->stream, 0);
    expect(stream_get_contents($this->stream))->to->equal('');
});

rit('stops waiting for the stream when the strand is terminated', function () {
    $temp = tempnam(sys_get_temp_dir(), 'recoil-test-fifo-');
    unlink($temp);
    posix_mkfifo($temp, 0644);
    $stream = fopen($temp, 'w+'); // must be w+ (read/write) to prevent blocking
    stream_set_blocking($stream, false);

    // fill the write buffer
    do {
        $bytes = fwrite(
            $stream,
            str_repeat('.', 8192)
        );
    } while ($bytes > 0);

    $strand = yield Recoil::execute(function () use ($stream) {
        yield Recoil::write($stream, '<buffer>');
        expect(false)->to->be->ok('strand was not terminated');
    });

    yield;

    $strand->terminate();

    // drain the stream to prevent the writing strand from blocking forever if
    // the termination doesn't work
    do {
        $buffer = fread($stream, 8192);
    } while ($buffer != '');

    @unlink($temp);
});

rit('synchronises access across multiple strands', function () {
    $temp = tempnam(sys_get_temp_dir(), 'recoil-test-fifo-');
    unlink($temp);
    posix_mkfifo($temp, 0644);
    $stream = fopen($temp, 'w+'); // must be w+ (read/write) to prevent blocking
    stream_set_blocking($stream, false);

    // fill the write buffer
    $size = 0;
    do {
        $bytes = fwrite(
            $stream,
            str_repeat('X', 8192)
        );
        $size += $bytes;
    } while ($bytes > 0);

    // queue up two write calls, both with buffers larger than the write buffer
    // size ...
    $bufferA = str_repeat('A', $size + 1);
    $bufferB = str_repeat('B', $size + 1);

    yield Recoil::execute(function () use ($stream, $bufferA) {
        yield Recoil::write($stream, $bufferA);
    });

    yield Recoil::execute(function () use ($stream, $bufferB) {
        yield Recoil::write($stream, $bufferB);
    });

    // read data from the strands, allowing the write strands to use the stream
    // ensure that we read less than the buffer size so that we don't read all
    // the data before its been written ...
    $buffer = '';
    do {
        $data = fread($stream, $size - 1);
        $buffer .= $data;
        yield;
    } while ($data != '');

    fclose($stream);
    unlink($temp);

    $padding = str_repeat('X', $size);

    // we don't know which order the strands will write their data, but the
    // content should never be jumbled ...
    expect(
        $buffer === $padding . $bufferA . $bufferB ||
        $buffer === $padding . $bufferB . $bufferA
    )->to->be->true('buffer does not match expected value');
});

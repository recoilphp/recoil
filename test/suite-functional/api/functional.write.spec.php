<?php

declare (strict_types = 1); // @codeCoverageIgnore

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
            str_repeat('.', 8192)
        );
        $size += $bytes;
    } while ($bytes > 0);

    // this has to be big enough to exceed the length able to be written
    // in a single call to fwrite()
    $writeBuffer = str_repeat('<chunk>', $size);

    yield Recoil::execute(function () use ($stream, $writeBuffer) {
        yield Recoil::write($stream, $writeBuffer);
    });

    yield Recoil::execute(function () use ($stream, $writeBuffer) {
        yield Recoil::write($stream, $writeBuffer);
    });

    // free up a single byte of write buffer at a time and yield to allow the
    // writing strands to execute. in order to verify that access is truly
    // synchronised, we can not allow either strand to write their entire buffer
    // in a single call to fwrite()
    do {
        expect(fread($stream, 1))->to->satisfy('is_string');
        yield;
    } while (--$size);

    // keep reading to get the actual data written by the strands
    $buffer = '';
    do {
        $data = fread($stream, 8192);
        $buffer .= $data;
        yield;
    } while ($data != '');

    // we don't know which order the strands will write their data, but the
    // content should never be jumbled ...
    expect(strlen($buffer))->to->equal(strlen($writeBuffer) * 2);
    expect($buffer)->to->equal($writeBuffer . $writeBuffer);
});

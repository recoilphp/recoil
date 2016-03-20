<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

beforeEach(function () {
    $this->stream = tmpfile();
    stream_set_read_buffer($this->stream, 0);
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

rit('returns the number of bytes written', function () {
    expect(yield Recoil::write($this->stream, '<buffer>'))->to->equal(8);
});

rit('only writes up to the specified maximum length', function () {
    expect(yield Recoil::write($this->stream, '<buffer>', 4))->to->equal(4);
    fseek($this->stream, 0);
    expect(stream_get_contents($this->stream))->to->equal('<buf');
});

if (extension_loaded('posix')) {
    rit('stops waiting for the stream when the strand is terminated', function () {
        $temp = tempnam(sys_get_temp_dir(), 'recoil-test-fifo-');
        unlink($temp);
        posix_mkfifo($temp, 0644);
        $stream = fopen($temp, 'w+');
        stream_set_read_buffer($stream, 0);
        stream_set_blocking($stream, false);

        $strand = yield Recoil::execute(function () use ($stream) {
            yield Recoil::write($stream, '<buffer>');
            assert(false, 'strand not terminated');
        });

        yield;

        $strand->terminate();

        @unlink($temp);
    });
} else {
    xit(
        'stops waiting for the stream when the strand is terminated (requires posix extension)',
        function () {}
    );
}

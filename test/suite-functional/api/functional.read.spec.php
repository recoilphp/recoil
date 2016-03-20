<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

beforeEach(function () {
    $this->content = file_get_contents(__FILE__);
    $this->stream = fopen(__FILE__, 'r');
    stream_set_read_buffer($this->stream, 0);
    stream_set_blocking($this->stream, false);
});

afterEach(function () {
    fclose($this->stream);
});

rit('reads from the stream', function () {
    expect(yield Recoil::read($this->stream))->to->equal($this->content);
});

rit('can be invoked by yielding a stream', function () {
    expect(yield $this->stream)->to->equal($this->content);
});

rit('only reads up to the specified maximum length', function () {
    expect(yield Recoil::read($this->stream, 16))->to->equal(substr($this->content,  0, 16));
    expect(yield Recoil::read($this->stream, 16))->to->equal(substr($this->content, 16, 16));
});

rit('returns an empty string at eof', function () {
    $content = '';

    do {
        $buffer = yield Recoil::read($this->stream);
        $content .= $buffer;
    } while ($buffer);

    expect($content)->to->equal($this->content);
});

if (extension_loaded('posix')) {
    rit('stops waiting for the stream when the strand is terminated', function () {
        $temp = tempnam(sys_get_temp_dir(), 'recoil-test-fifo-');
        unlink($temp);
        posix_mkfifo($temp, 0644);
        $stream = fopen($temp, 'w+'); // must be w+ (read/write) to prevent blocking
        stream_set_read_buffer($stream, 0);
        stream_set_blocking($stream, false);

        $strand = yield Recoil::execute(function () use ($stream) {
            yield Recoil::read($stream);
            assert(false, 'strand was not terminated');
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

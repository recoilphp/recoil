<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil;

use Recoil\Exception\TimeoutException;

beforeEach(function () {
    $this->filename = tempnam(sys_get_temp_dir(), 'recoil-test-fifo-');
    unlink($this->filename);
    posix_mkfifo($this->filename, 0644);
    $this->stream = fopen($this->filename, 'w+'); // must be w+ (read/write) to prevent blocking
    stream_set_blocking($this->stream, false);
});

afterEach(function () {
    fclose($this->stream);
    unlink($this->filename);
});

context('when reading', function () {
    rit('resumes the strand when the stream is ready', function () {
        yield Recoil::execute(function () {
            yield;
            fwrite($this->stream, 'a');
        });

        yield Recoil::select([$this->stream]);
        expect(fread($this->stream, 2))->to->equal('a');
    });

    rit('returns an array containing the stream', function () {
        fwrite($this->stream, 'a');

        $result = yield Recoil::select([$this->stream]);
        expect($result)->to->equal([
            [$this->stream],
            [],
        ]);
    });

    rit('allows the strand to be terminated', function () {
        $strand = yield Recoil::execute(function () use (&$count) {
            yield Recoil::select([$this->stream]);
            expect(false)->to->be->ok('strand was not terminated');
        });

        yield;

        $strand->terminate();

        // write to the stream to prevent the reading strand from blocking forever
        // if termination doesn't work
        fwrite($this->stream, 'a');
    });

    context('when a timeout is specified', function () {
        rit('behaves normally if the stream is ready', function () {
            fwrite($this->stream, 'a');

            $result = yield Recoil::select([$this->stream], null, 0.05);
            expect($result)->to->equal([
                [$this->stream],
                [],
            ]);
        });

        rit('resumes the strand with an exception on timeout', function () {
            try {
                yield Recoil::select([$this->stream], null, 0.05);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (TimeoutException $e) {
                expect($e->getMessage())->to->equal('The operation timed out after 0.05 second(s).');
            }
        });

        rit('allows the strand to be terminated', function () {
            $strand = yield Recoil::execute(function () use (&$count) {
                yield Recoil::select([$this->stream], null, 0.05);
                expect(false)->to->be->ok('strand was not terminated');
            });

            yield;

            $strand->terminate();

            // write to the stream to prevent the reading strand from blocking forever
            // if termination doesn't work
            fwrite($this->stream, 'a');
        });
    });
});

context('when writing', function () {
    rit('resumes the strand when the stream is ready', function () {
        // fill the write buffer
        do {
            $bytes = fwrite(
                $this->stream,
                str_repeat('.', 8192)
            );
        } while ($bytes > 0);

        yield Recoil::execute(function () {
            yield;
            fread($this->stream, 8192);
        });

        yield Recoil::select(null, [$this->stream]);
        expect(fwrite($this->stream, '.'))->to->equal(1);
    });

    rit('returns an array containing the stream', function () {
        $result = yield Recoil::select(null, [$this->stream]);
        expect($result)->to->equal([
            [],
            [$this->stream],
        ]);
    });

    rit('allows the strands to be terminated', function () {
        // fill the write buffer
        do {
            $bytes = fwrite(
                $this->stream,
                str_repeat('.', 8192)
            );
        } while ($bytes > 0);

        $strand = yield Recoil::execute(function () use (&$count) {
            yield Recoil::select(null, [$this->stream]);
            expect(false)->to->be->ok('strand was not terminated');
        });

        yield;

        $strand->terminate();

        // drain the stream to prevent the writing strand from blocking forever if
        // the termination doesn't work
        do {
            $buffer = fread($this->stream, 8192);
        } while ($buffer != '');
    });

    context('when a timeout is specified', function () {
        rit('behaves normally if the stream is ready', function () {
            $result = yield Recoil::select(null, [$this->stream], 0.05);
            expect($result)->to->equal([
                [],
                [$this->stream],
            ]);
        });

        rit('resumes the strand with an exception on timeout', function () {
            // fill the write buffer
            do {
                $bytes = fwrite(
                    $this->stream,
                    str_repeat('.', 8192)
                );
            } while ($bytes > 0);

            try {
                yield Recoil::select(null, [$this->stream], 0.05);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (TimeoutException $e) {
                expect($e->getMessage())->to->equal('The operation timed out after 0.05 second(s).');
            }
        });
    });
});

rit('resumes immediately if no streams are passed', function () {
    $strand = yield Recoil::execute(function () {
        $result = yield Recoil::select();
        expect($result)->to->equal([[], []]);
    });

    yield;

    expect($strand->hasExited())->to->be->true;
});

rit('resumes immediately if empty arrays are passed', function () {
    $strand = yield Recoil::execute(function () {
        $result = yield Recoil::select([], []);
        expect($result)->to->equal([[], []]);
    });

    yield;

    expect($strand->hasExited())->to->be->true;
});

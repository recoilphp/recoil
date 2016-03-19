<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Eloquent\Phony\Phony;
use Exception;

beforeEach(function () {
    $this->spy = Phony::spy();
    $this->fn1 = function () {
        ($this->spy)(1);
        yield 0.001;
        ($this->spy)(3);

        return 'a';
    };
    $this->fn2 = function () {
        ($this->spy)(2);
        yield 0.002;
        ($this->spy)(4);

        return 'b';
    };
});

rit('executes coroutines concurrently', function () {
    yield Recoil::all(
        ($this->fn1)(),
        ($this->fn2)()
    );

    Phony::inOrder(
        $this->spy->calledWith(1),
        $this->spy->calledWith(2),
        $this->spy->calledWith(3),
        $this->spy->calledWith(4)
    );
});

rit('can be invoked by yielding an array', function () {
    yield [
        ($this->fn1)(),
        ($this->fn2)(),
    ];

    Phony::inOrder(
        $this->spy->calledWith(1),
        $this->spy->calledWith(2),
        $this->spy->calledWith(3),
        $this->spy->calledWith(4)
    );
});

rit('returns an array of return values', function () {
    expect(yield Recoil::all(
        ($this->fn1)(),
        ($this->fn2)()
    ))->to->equal(['a', 'b']);
});

rit('array keys match argument order', function () {
    expect(yield Recoil::all(
        ($this->fn2)(),
        ($this->fn1)()
    ))->to->equal([1 => 'a', 0 => 'b']);
});

context('when one of the coroutines throws an exception', function () {
    beforeEach(function () {
        $this->fn2 = function () {
            throw new Exception('<exception>');
            yield;
        };
    });

    rit('propagates the exception', function () {
        try {
            yield Recoil::all(
                ($this->fn1)(),
                ($this->fn2)()
            );
            assert(false, 'Expected exception was not thrown.');
        } catch (Exception $e) {
            expect($e->getMessage())->to->equal('<exception>');
        }
    });

    rit('terminates the other strand', function () {
        try {
            yield Recoil::all(
                ($this->fn1)(),
                ($this->fn2)()
            );
        } catch (Exception $e) {
            // fall-through ...
        }

        $this->spy->never()->calledWith(3);
    });
});

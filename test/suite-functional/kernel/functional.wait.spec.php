<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Kernel\Strand;

describe('->wait()', function () {
    it('waits for all strands to exit', function () {
        $this->kernel->execute(function () {
            echo 'a';

            return;
            yield;
        });
        $this->kernel->execute(function () {
            echo 'b';

            return;
            yield;
        });

        ob_start();
        $this->kernel->wait();
        expect(ob_get_clean())->to->equal('ab');
    });

    it('returns true when all strands have exited', function () {
        $this->kernel->execute(function () {
            return;
            yield;
        });

        expect($this->kernel->wait())->to->be->true;
    });
});

describe('->waitForStrand()', function () {
    it('returns the strand entry-point return value', function () {
        $strand = $this->kernel->execute(function () {
            return '<ok>';
            yield;
        });

        expect($this->kernel->waitForStrand($strand))->to->equal('<ok>');
    });

    it('propagates uncaught exceptions', function () {
        $strand = $this->kernel->execute(function () {
            throw new Exception('<exception>');
            yield;
        });

        try {
            $this->kernel->waitForStrand($strand);
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (Exception $e) {
            expect($e->getMessage())->to->equal('<exception>');
        }
    });

    it('executes all strands', function () {
        $this->kernel->execute(function () {
            echo 'a';

            return;
            yield;
        });
        $strand = $this->kernel->execute(function () {
            echo 'b';

            return;
            yield;
        });

        ob_start();
        $this->kernel->waitForStrand($strand);
        expect(ob_get_clean())->to->equal('ab');
    });

    it('returns after the strand has exited', function () {
        $strand = $this->kernel->execute(function () {
            echo 'a';

            return;
            yield;
        });
        $this->kernel->execute(function () {
            yield;
            echo 'b';
        });

        ob_start();
        $this->kernel->waitForStrand($strand);
        expect(ob_get_clean())->to->equal('a');
    });

    it('can be nested inside wait()', function () {
        $this->kernel->execute(function () {
            echo 'a';

            $strand = yield Recoil::execute(function () {
                yield; // ensure that this strand is delayed such
                       // that the waitForStrand call is actually necessary
                echo 'c';
            });

            echo 'b';
            $this->kernel->waitForStrand($strand);
            echo 'd';
        });

        ob_start();
        $this->kernel->wait();
        expect(ob_get_clean())->to->equal('abcd');
    });

    it('can be nested inside itself', function () {
        $this->kernel->execute(function () {
            echo 'a';

            $strand1 = yield Recoil::execute(function () {
                yield;
                echo 'd';
            });
            $strand2 = yield Recoil::execute(function () use ($strand1) {
                echo 'c';
                $this->kernel->waitForStrand($strand1);
                echo 'e';

                return;
                yield;
            });

            echo 'b';
            $this->kernel->waitForStrand($strand2);
            echo 'f';
        });

        ob_start();
        $this->kernel->wait();
        expect(ob_get_clean())->to->equal('abcdef');
    });
});

describe('->waitFor()', function () {
    it('returns the coroutine return value', function () {
        expect($this->kernel->waitFor(function () {
            return '<ok>';
            yield;
        }))->to->equal('<ok>');
    });

    it('propagates uncaught exceptions', function () {
        try {
            $this->kernel->waitFor(function () {
                throw new Exception('<exception>');
                yield;
            });
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (Exception $e) {
            expect($e->getMessage())->to->equal('<exception>');
        }
    });

    it('executes all strands', function () {
        $this->kernel->execute(function () {
            echo 'a';

            return;
            yield;
        });

        ob_start();
        $this->kernel->waitFor(function () {
            echo 'b';

            return;
            yield;
        });
        expect(ob_get_clean())->to->equal('ab');
    });

    it('returns after the strand has exited', function () {
        $this->kernel->execute(function () {
            yield;
            echo 'b';
        });

        ob_start();
        $this->kernel->waitFor(function () {
            echo 'a';

            return;
            yield;
        });
        expect(ob_get_clean())->to->equal('a');
    });

    it('can be nested inside wait()', function () {
        $this->kernel->execute(function () {
            echo 'a';
            $this->kernel->waitFor(function () {
                yield; // ensure that this strand is delayed such
                       // that the waitForStrand call is actually necessary
                echo 'b';
            });
            echo 'c';

            return;
            yield;
        });

        ob_start();
        $this->kernel->wait();
        expect(ob_get_clean())->to->equal('abc');
    });
});

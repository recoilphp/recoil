<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Kernel\Strand;

describe('->run()', function () {
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
        $this->kernel->run();
        expect(ob_get_clean())->to->equal('ab');
    });

    it('returns immediately if kernel is already running', function () {
        $this->kernel->execute(function () {
            yield;
            $this->kernel->run();
            // if this test fails, this function will block forever
        });

        $this->kernel->run();
    });
});

describe('->adoptSync()', function () {
    it('returns the strand entry-point return value', function () {
        $strand = $this->kernel->execute(function () {
            return '<ok>';
            yield;
        });

        expect($this->kernel->adoptSync($strand))->to->equal('<ok>');
    });

    it('propagates uncaught exceptions', function () {
        $strand = $this->kernel->execute(function () {
            throw new Exception('<exception>');
            yield;
        });

        try {
            $this->kernel->adoptSync($strand);
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
        $this->kernel->adoptSync($strand);
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
        $this->kernel->adoptSync($strand);
        expect(ob_get_clean())->to->equal('a');
    });

    it('returns if the strand has already exited', function () {
        $strand = $this->kernel->execute(function () {
            return '<ok>';
            yield;
        });

        $this->kernel->adoptSync($strand);

        expect($this->kernel->adoptSync($strand))->to->equal('<ok>');
    });

    it('can be nested inside run()', function () {
        $this->kernel->execute(function () {
            echo 'a';

            $strand = yield Recoil::execute(function () {
                yield; // ensure that this strand is delayed such that the
                       // adoptSync call is actually necessary
                echo 'c';
            });

            echo 'b';
            $this->kernel->adoptSync($strand);
            echo 'd';
        });

        ob_start();
        $this->kernel->run();
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
                $this->kernel->adoptSync($strand1);
                echo 'e';

                return;
                yield;
            });

            echo 'b';
            $this->kernel->adoptSync($strand2);
            echo 'f';
        });

        ob_start();
        $this->kernel->run();
        expect(ob_get_clean())->to->equal('abcdef');
    });
});

describe('->executeSync()', function () {
    it('returns the coroutine return value', function () {
        expect($this->kernel->executeSync(function () {
            return '<ok>';
            yield;
        }))->to->equal('<ok>');
    });

    it('propagates uncaught exceptions', function () {
        try {
            $this->kernel->executeSync(function () {
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
        $this->kernel->executeSync(function () {
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
        $this->kernel->executeSync(function () {
            echo 'a';

            return;
            yield;
        });
        expect(ob_get_clean())->to->equal('a');
    });

    it('can be nested inside run()', function () {
        $this->kernel->execute(function () {
            echo 'a';
            $this->kernel->executeSync(function () {
                yield; // ensure that this strand is delayed such that the
                       // executeSync call is actually necessary
                echo 'b';
            });
            echo 'c';

            return;
            yield;
        });

        ob_start();
        $this->kernel->run();
        expect(ob_get_clean())->to->equal('abc');
    });
});

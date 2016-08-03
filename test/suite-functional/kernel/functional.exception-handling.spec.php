<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
use Recoil\Kernel\Exception\KernelPanicException;
use Recoil\Kernel\Exception\StrandException;
use Recoil\Kernel\Strand;

beforeEach(function () {
    $this->exception = new Exception('<exception>');
    $this->strand = $this->kernel->execute(function () {
        throw $this->exception;
        yield;
    });
});

context('when there is no exception handler', function () {
    it('run() throws a StrandException', function () {
        try {
            $this->kernel->run();
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e->strand())->to->equal($this->strand);
            expect($e->getPrevious() === $this->exception)->to->be->true;
        }
    });

    it('adoptSync() throws a StrandException', function () {
        try {
            $otherStrand = $this->kernel->execute(function () {
                yield;
            });
            $this->kernel->adoptSync($otherStrand);
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e->strand())->to->equal($this->strand);
            expect($e->getPrevious() === $this->exception)->to->be->true;
        }
    });

    it('executeSync() throws a StrandException', function () {
        try {
            $this->kernel->executeSync(function () {
                yield;
            });
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e->strand())->to->equal($this->strand);
            expect($e->getPrevious() === $this->exception)->to->be->true;
        }
    });
});

context('when there is an exception handler set', function () {
    beforeEach(function () {
        $this->handledStrand = null;
        $this->handledException = null;

        $this->kernel->setExceptionHandler(function ($strand, $exception) {
            $this->handledStrand = $strand;
            $this->handledException = $exception;
        });
    });

    it('is passed the strand and exception as arguments', function () {
        $this->kernel->run();
        expect($this->handledStrand === $this->strand)->to->be->true;
        expect($this->handledException === $this->exception)->to->be->true;
    });

    context('when the exception is handled', function () {
        it('run() does not throw', function () {
            $this->kernel->run();
        });

        it('adoptSync() does not throw', function () {
            $otherStrand = $this->kernel->execute(function () {
                yield;
            });
            $this->kernel->adoptSync($otherStrand);
        });

        it('executeSync() does not throw', function () {
            $this->kernel->executeSync(function () {
                yield;
            });
        });
    });

    context('when the exception handler rethrows the exception', function () {
        beforeEach(function () {
            $this->kernel->setExceptionHandler(function ($strand, $exception) {
                throw $exception;
            });
        });

        it('run() throws a StrandException', function () {
            try {
                $this->kernel->run();
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (StrandException $e) {
                expect($e->strand())->to->equal($this->strand);
                expect($e->getPrevious() === $this->exception)->to->be->true;
            }
        });

        it('adoptSync() throws a StrandException', function () {
            try {
                $otherStrand = $this->kernel->execute(function () {
                    yield;
                });
                $this->kernel->adoptSync($otherStrand);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (StrandException $e) {
                expect($e->strand())->to->equal($this->strand);
                expect($e->getPrevious())->to->equal($this->exception);
            }
        });

        it('executeSync() throws a StrandException', function () {
            try {
                $this->kernel->executeSync(function () {
                    yield;
                });
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (StrandException $e) {
                expect($e->strand())->to->equal($this->strand);
                expect($e->getPrevious() === $this->exception)->to->be->true;
            }
        });
    });

    context('when the exception handler throws a different exception', function () {
        beforeEach(function () {
            $this->handlerException = new Exception('<handler>');
            $this->kernel->setExceptionHandler(function ($strand, $exception) {
                throw $this->handlerException;
            });
        });

        it('run() throws a KernelPanicException', function () {
            try {
                $this->kernel->run();
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (KernelPanicException $e) {
                expect($e->getPrevious() === $this->handlerException)->to->be->true;
            }
        });

        it('adoptSync() throws a KernelPanicException', function () {
            try {
                $otherStrand = $this->kernel->execute(function () {
                    yield;
                });
                $this->kernel->adoptSync($otherStrand);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (KernelPanicException $e) {
                expect($e->getPrevious() === $this->handlerException)->to->be->true;
            }
        });

        it('executeSync() throws a KernelPanicException', function () {
            try {
                $this->kernel->executeSync(function () {
                    yield;
                });
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (KernelPanicException $e) {
                expect($e->getPrevious() === $this->handlerException)->to->be->true;
            }
        });
    });

});

context('when the kernel is started recursively', function () {
});

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
    it('causes run() to throw an exception', function () {
        try {
            $this->kernel->run();
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e->strand())->to->equal($this->strand);
            expect($e->getPrevious() === $this->exception)->to->be->true;
        }
    });

    xit('causes run() to throw the same exception if invoked subsequently', function () {
        try {
            $this->kernel->run();
        } catch (StrandException $exception) {
            // ok ...
        }

        try {
            $this->kernel->run();
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e === $exception)->to->be->true;
        }
    });

    it('causes adoptSync() to throw an exception', function () {
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

    xit('causes waitForStrand() to throw the same exception if invoked subsequently', function () {
        try {
            $this->kernel->run();
        } catch (StrandException $exception) {
            // ok ...
        }

        try {
            $otherStrand = $this->kernel->execute(function () {
                yield;
            });
            $this->kernel->waitForStrand($otherStrand);
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e === $exception)->to->be->true;
        }
    });

    it('causes executeSync() to throw an exception', function () {
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

    xit('causes waitFor() to throw the same exception if invoked subsequently', function () {
        try {
            $this->kernel->run();
        } catch (StrandException $exception) {
            // ok ...
        }

        try {
            $this->kernel->waitFor(function () {
                yield;
            });
            $this->kernel->waitForStrand($otherStrand);
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e === $exception)->to->be->true;
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

    it('prevents run() from throwing', function () {
        $this->kernel->run();
    });

    it('prevents adoptSync() from throwing', function () {
        $otherStrand = $this->kernel->execute(function () {
            yield;
        });
        $this->kernel->adoptSync($otherStrand);
    });

    it('prevents executeSync() from throwing', function () {
        $this->kernel->executeSync(function () {
            yield;
        });
    });

    it('is passed the strand and exception as an arguments', function () {
        $this->kernel->run();
        expect($this->handledStrand === $this->strand)->to->be->true;
        expect($this->handledException === $this->exception)->to->be->true;
    });

    context('when the exception handler rethrows the exception', function () {
        beforeEach(function () {
            $this->kernel->setExceptionHandler(function ($strand, $exception) {
                throw $exception;
            });
        });

        it('throws a StrandException from run()', function () {
            try {
                $this->kernel->run();
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (StrandException $e) {
                expect($e->strand())->to->equal($this->strand);
                expect($e->getPrevious() === $this->exception)->to->be->true;
            }
        });

        it('throws a StrandException from adoptSync()', function () {
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

        it('throws a StrandException from executeSync', function () {
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

        xit('causes wait() to throw the same exception if invoked subsequently', function () {
            try {
                $this->kernel->run();
            } catch (StrandException $exception) {
                // ok ...
            }

            try {
                $this->kernel->run();
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (StrandException $e) {
                expect($e === $exception)->to->be->true;
            }
        });

        xit('causes waitForStrand() to throw the same exception if invoked subsequently', function () {
            try {
                $this->kernel->run();
            } catch (StrandException $exception) {
                // ok ...
            }

            try {
                $otherStrand = $this->kernel->execute(function () {
                    yield;
                });
                $this->kernel->waitForStrand($otherStrand);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (StrandException $e) {
                expect($e === $exception)->to->be->true;
            }
        });

        xit('causes waitFor() to throw the same exception if invoked subsequently', function () {
            try {
                $this->kernel->run();
            } catch (StrandException $exception) {
                // ok ...
            }

            try {
                $this->kernel->waitFor(function () {
                    yield;
                });
                $this->kernel->waitForStrand($otherStrand);
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (StrandException $e) {
                expect($e === $exception)->to->be->true;
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

        it('throws a KernelPanicException from run()', function () {
            try {
                $this->kernel->run();
                expect(false)->to->be->ok('expected exception was not thrown');
            } catch (KernelPanicException $e) {
                expect($e->getPrevious() === $this->handlerException)->to->be->true;
            }
        });

        it('throws a KernelPanicException from adoptSync()', function () {
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

        it('throws a KernelPanicException from executeSync', function () {
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

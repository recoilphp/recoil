<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil;

use Exception;
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
    it('causes wait() to throw an exception', function () {
        try {
            $this->kernel->wait();
            assert(false, 'expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e->strand())->to->equal($this->strand);
            assert($e->getPrevious() === $this->exception);
        }
    });

    it('causes wait() to throw the same exception if invoked subsequently', function () {
        try {
            $this->kernel->wait();
        } catch (StrandException $exception) {
            // ok ...
        }

        try {
            $this->kernel->wait();
            assert(false, 'expected exception was not thrown');
        } catch (StrandException $e) {
            assert($e === $exception);
        }
    });

    it('causes waitForStrand() to throw an exception', function () {
        try {
            $otherStrand = $this->kernel->execute(function () {
                yield;
            });
            $this->kernel->waitForStrand($otherStrand);
            assert(false, 'expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e->strand())->to->equal($this->strand);
            assert($e->getPrevious() === $this->exception);
        }
    });

    it('causes waitForStrand() to throw the same exception if invoked subsequently', function () {
        try {
            $this->kernel->wait();
        } catch (StrandException $exception) {
            // ok ...
        }

        try {
            $otherStrand = $this->kernel->execute(function () {
                yield;
            });
            $this->kernel->waitForStrand($otherStrand);
            assert(false, 'expected exception was not thrown');
        } catch (StrandException $e) {
            assert($e === $exception);
        }
    });

    it('causes waitFor() to throw an exception', function () {
        try {
            $this->kernel->waitFor(function () {
                yield;
            });
            assert(false, 'expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e->strand())->to->equal($this->strand);
            assert($e->getPrevious() === $this->exception);
        }
    });

    it('causes waitFor() to throw the same exception if invoked subsequently', function () {
        try {
            $this->kernel->wait();
        } catch (StrandException $exception) {
            // ok ...
        }

        try {
            $this->kernel->waitFor(function () {
                yield;
            });
            $this->kernel->waitForStrand($otherStrand);
            assert(false, 'expected exception was not thrown');
        } catch (StrandException $e) {
            assert($e === $exception);
        }
    });
});

context('when there is an exception handler set', function () {
    beforeEach(function () {
        $this->handledException = null;

        $this->kernel->setExceptionHandler(function ($exception) {
            $this->handledException = $exception;

            return true;
        });
    });

    it('does not cause wait() to throw', function () {
        $this->kernel->wait();
    });

    it('does not cause waitForStrand() to throw', function () {
        $otherStrand = $this->kernel->execute(function () {
            yield;
        });
        $this->kernel->waitForStrand($otherStrand);
    });

    it('does not cause waitFor() to throw', function () {
        $this->kernel->waitFor(function () {
            yield;
        });
    });

    it('is passed the exception as an argument', function () {
        $this->kernel->wait();
        assert($this->handledException === $this->exception);
    });

    context('when the exception handler returns false', function () {
        beforeEach(function () {
            $this->kernel->setExceptionHandler(function () {
                return false;
            });
        });

        it('causes wait() to throw an exception', function () {
            try {
                $this->kernel->wait();
                assert(false, 'expected exception was not thrown');
            } catch (StrandException $e) {
                expect($e->strand())->to->equal($this->strand);
                assert($e->getPrevious() === $this->exception);
            }
        });

        it('causes wait() to throw the same exception if invoked subsequently', function () {
            try {
                $this->kernel->wait();
            } catch (StrandException $exception) {
                // ok ...
            }

            try {
                $this->kernel->wait();
                assert(false, 'expected exception was not thrown');
            } catch (StrandException $e) {
                assert($e === $exception);
            }
        });

        it('causes waitForStrand() to throw an exception', function () {
            try {
                $otherStrand = $this->kernel->execute(function () {
                    yield;
                });
                $this->kernel->waitForStrand($otherStrand);
                assert(false, 'expected exception was not thrown');
            } catch (StrandException $e) {
                expect($e->strand())->to->equal($this->strand);
                expect($e->getPrevious())->to->equal($this->exception);
            }
        });

        it('causes waitForStrand() to throw the same exception if invoked subsequently', function () {
            try {
                $this->kernel->wait();
            } catch (StrandException $exception) {
                // ok ...
            }

            try {
                $otherStrand = $this->kernel->execute(function () {
                    yield;
                });
                $this->kernel->waitForStrand($otherStrand);
                assert(false, 'expected exception was not thrown');
            } catch (StrandException $e) {
                assert($e === $exception);
            }
        });

        it('causes waitFor() to throw an exception', function () {
            try {
                $this->kernel->waitFor(function () {
                    yield;
                });
                assert(false, 'expected exception was not thrown');
            } catch (StrandException $e) {
                expect($e->strand())->to->equal($this->strand);
                assert($e->getPrevious() === $this->exception);
            }
        });

        it('causes waitFor() to throw the same exception if invoked subsequently', function () {
            try {
                $this->kernel->wait();
            } catch (StrandException $exception) {
                // ok ...
            }

            try {
                $this->kernel->waitFor(function () {
                    yield;
                });
                $this->kernel->waitForStrand($otherStrand);
                assert(false, 'expected exception was not thrown');
            } catch (StrandException $e) {
                assert($e === $exception);
            }
        });
    });
});

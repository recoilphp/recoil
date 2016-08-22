<?php

declare(strict_types=1); // @codeCoverageIgnore

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
});

context('when there is an exception handler set', function () {
    beforeEach(function () {
        $this->handledException = null;

        $this->kernel->setExceptionHandler(function ($exception) {
            $this->handledException = $exception;
        });
    });

    it('is passed a StrandException argument when a strand exits with an exception', function () {
        $this->kernel->run();
        expect($this->handledException instanceof StrandException)->to->be->true;
        expect($this->handledException->strand())->to->equal($this->strand);
        expect($this->handledException->getPrevious() === $this->exception)->to->be->true;
    });

    it('is passed a KernelPanicException when an internal error occurs', function () {
        $this->kernel->throw($this->exception, null);
        $this->kernel->run();
        expect($this->handledException instanceof KernelPanicException)->to->be->true;
        expect(!$this->handledException instanceof StrandException)->to->be->false;
        expect($this->handledException->getPrevious() === $this->exception)->to->be->true;
    });

    context('when the exception is handled', function () {
        it('run() does not throw', function () {
            $this->kernel->run();
        });
    });

    context('when the exception handler rethrows the exception', function () {
        beforeEach(function () {
            $this->kernel->setExceptionHandler(function ($exception) {
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
    });

    context('when the exception handler throws a different exception', function () {
        beforeEach(function () {
            $this->handlerException = new Exception('<handler>');
            $this->kernel->setExceptionHandler(function ($exception) {
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
    });
});

context('when there are multiple unhandled exceptions', function () {
    it('subsequent exceptions are thrown on the next attempt ot run the kernel', function () {
        $exception = new Exception('<another-exception>');
        $strand = $this->kernel->execute(function () use ($exception) {
            throw $exception;
            yield;
        });

        try {
            $this->kernel->run();
        } catch (StrandException $e) {
            // ok ...
        }

        try {
            $this->kernel->run();
            expect(false)->to->be->ok('expected exception was not thrown');
        } catch (StrandException $e) {
            expect($e->strand())->to->equal($strand);
            expect($e->getPrevious() === $exception)->to->be->true;
        }
    });
});

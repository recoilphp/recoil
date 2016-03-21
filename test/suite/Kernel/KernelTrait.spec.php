<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Eloquent\Phony\Phony;
use Exception;
use Recoil\Exception\TerminatedException;
use RuntimeException;

describe(KernelTrait::class, function () {

    beforeEach(function () {
        $this->strand = Phony::mock(Strand::class);

        $this->subject = Phony::partialMock(
            [Kernel::class, KernelTrait::class]
        );

        $this->strand->kernel->returns($this->subject);
    });

    describe('->waitForStrand()', function () {
        it('sets the strand observer before waiting', function () {
            $this->strand->setObserver->does(
                function ($observer) {
                    $observer->success($this->strand->mock(), null);
                }
            );

            $this->subject->mock()->waitForStrand($this->strand->mock());

            Phony::inOrder(
                $this->strand->setObserver->called(),
                $this->subject->wait->called()
            );
        });

        it('returns the coroutine result', function () {
            $this->strand->setObserver->does(
                function ($observer) {
                    $observer->success($this->strand->mock(), '<ok>');
                }
            );

            $result = $this->subject->mock()->waitForStrand($this->strand->mock());
            $this->subject->stop->called();
            expect($result)->to->equal('<ok>');
        });

        it('propagates uncaught exceptions', function () {
            $this->strand->setObserver->does(
                function ($observer) {
                    $observer->failure(
                        $this->strand->mock(),
                        new Exception('<exception>')
                    );
                }
            );

            expect(function () {
                $this->subject->mock()->waitForStrand($this->strand->mock());
            })->to->throw(
                Exception::class,
                '<exception>'
            );

            $this->subject->stop->called();
        });

        it('throws an exception if the strand is terminated', function () {
            $this->strand->setObserver->does(
                function ($observer) {
                    $observer->terminated($this->strand->mock());
                }
            );

            expect(function () {
                $this->subject->mock()->waitForStrand($this->strand->mock());
            })->to->throw(TerminatedException::class);

            $this->subject->stop->called();
        });

        it('detects abandoned strands', function () {
            expect(function () {
                $this->subject->mock()->waitForStrand($this->strand->mock());
            })->to->throw(
                RuntimeException::class,
                'The strand never exited.'
            );

            $this->subject->stop->never()->called();
        });
    });

    describe('->waitFor()', function () {
        it('creates a new strand and waits for it', function () {
            $this->subject->execute->returns($this->strand);

            $this->subject->waitForStrand->returns('<ok>');
            $result = $this->subject->mock()->waitFor('<coroutine>');

            Phony::inOrder(
                $this->subject->execute->calledWith('<coroutine>'),
                $this->subject->waitForStrand->calledWith($this->strand)
            );
            expect($result)->to->equal('<ok>');
        });
    });

});

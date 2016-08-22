<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\React;

use Eloquent\Phony\Phony;
use React\EventLoop\LoopInterface;

describe(StreamQueue::class, function () {
    beforeEach(function () {
        $this->eventLoop = Phony::mock(LoopInterface::class);
        $this->readStream = tmpfile();
        $this->writeStream = tmpfile();

        $this->subject = new StreamQueue(
            $this->eventLoop->get()
        );
    });

    context('->read()', function () {
        it('adds the stream to the event loop', function () {
            $this->subject->read($this->readStream, function () {
            });
            $this->eventLoop->addReadStream->calledWith($this->readStream, '~');
        });

        it('only adds the stream to the event loop one', function () {
            $this->subject->read($this->readStream, function () {
            });
            $this->subject->read($this->readStream, function () {
            });
            $this->eventLoop->addReadStream->once()->called();
        });

        it('removes the stream from the event loop once all callbacks are done', function () {
            $done1 = $this->subject->read($this->readStream, function () {
            });
            $done2 = $this->subject->read($this->readStream, function () {
            });

            $done1();
            $this->eventLoop->removeReadStream->never()->called();

            $done2();
            $this->eventLoop->removeReadStream->calledWith($this->readStream);
        });

        context('when the stream is ready', function () {
            it('calls the callback at the head of the queue', function () {
                $spy1 = Phony::spy();
                $spy2 = Phony::spy();

                $this->subject->read($this->readStream, $spy1);
                $this->subject->read($this->readStream, $spy2);

                // Capture the function that is registered with the event loop ...
                $fn = $this->eventLoop->addReadStream->called()->firstCall()->argument(1);
                $fn($this->readStream);

                $spy1->calledWith($this->readStream);
                $spy2->never()->called();
            });

            it('calls the same callback if it is not done', function () {
                $spy1 = Phony::spy();
                $spy2 = Phony::spy();

                $this->subject->read($this->readStream, $spy1);
                $this->subject->read($this->readStream, $spy2);

                // Capture the function that is registered with the event loop ...
                $fn = $this->eventLoop->addReadStream->called()->firstCall()->argument(1);
                $fn($this->readStream);
                $fn($this->readStream);

                $spy1->twice()->called();
                $spy2->never()->called();
            });

            it('moves onto the next callback when the head is done', function () {
                $spy1 = Phony::spy();
                $spy2 = Phony::spy();

                $done = $this->subject->read($this->readStream, $spy1);
                $this->subject->read($this->readStream, $spy2);

                $done();

                // Capture the function that is registered with the event loop ...
                $fn = $this->eventLoop->addReadStream->called()->firstCall()->argument(1);
                $fn($this->readStream);

                $spy1->never()->called();
                $spy2->calledWith($this->readStream);
            });
        });
    });

    context('->write()', function () {
        it('adds the stream to the event loop', function () {
            $this->subject->write($this->writeStream, function () {
            });
            $this->eventLoop->addWriteStream->calledWith($this->writeStream, '~');
        });

        it('only adds the stream to the event loop one', function () {
            $this->subject->write($this->writeStream, function () {
            });
            $this->subject->write($this->writeStream, function () {
            });
            $this->eventLoop->addWriteStream->once()->called();
        });

        it('removes the stream from the event loop once all callbacks are done', function () {
            $done1 = $this->subject->write($this->writeStream, function () {
            });
            $done2 = $this->subject->write($this->writeStream, function () {
            });

            $done1();
            $this->eventLoop->removeWriteStream->never()->called();

            $done2();
            $this->eventLoop->removeWriteStream->calledWith($this->writeStream);
        });

        context('when the stream is ready', function () {
            it('calls the callback at the head of the queue', function () {
                $spy1 = Phony::spy();
                $spy2 = Phony::spy();

                $this->subject->write($this->writeStream, $spy1);
                $this->subject->write($this->writeStream, $spy2);

                // Capture the function that is registered with the event loop ...
                $fn = $this->eventLoop->addWriteStream->called()->firstCall()->argument(1);
                $fn($this->writeStream);

                $spy1->calledWith($this->writeStream);
                $spy2->never()->called();
            });

            it('calls the same callback if it is not done', function () {
                $spy1 = Phony::spy();
                $spy2 = Phony::spy();

                $this->subject->write($this->writeStream, $spy1);
                $this->subject->write($this->writeStream, $spy2);

                // Capture the function that is registered with the event loop ...
                $fn = $this->eventLoop->addWriteStream->called()->firstCall()->argument(1);
                $fn($this->writeStream);
                $fn($this->writeStream);

                $spy1->twice()->called();
                $spy2->never()->called();
            });

            it('moves onto the next callback when the head is done', function () {
                $spy1 = Phony::spy();
                $spy2 = Phony::spy();

                $done = $this->subject->write($this->writeStream, $spy1);
                $this->subject->write($this->writeStream, $spy2);

                $done();

                // Capture the function that is registered with the event loop ...
                $fn = $this->eventLoop->addWriteStream->called()->firstCall()->argument(1);
                $fn($this->writeStream);

                $spy1->never()->called();
                $spy2->calledWith($this->writeStream);
            });
        });
    });
});

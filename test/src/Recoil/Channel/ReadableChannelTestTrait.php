<?php

namespace Recoil\Channel;

use Recoil\Channel\Exception\ChannelClosedException;
use Recoil\Recoil;

trait ReadableChannelTestTrait
{
    public function testCloseWithPendingRead()
    {
        $output = [];

        Recoil::run(
            function () use (&$output) {
                $reader = function () use (&$output) {
                    try {
                        $output[] = 'reading';
                        yield $this->channel->read();
                    } catch (ChannelClosedException $e) {
                        $output[] = 'closed';
                    }
                };

                $closer = function () use (&$output) {
                    $output[] = 'closing';
                    yield $this->channel->close();
                };

                yield Recoil::execute($reader());
                yield Recoil::execute($closer());
            }
        );

        $this->assertEquals(
            ['reading', 'closing', 'closed'],
            $output
        );
    }

    public function testReadWhenClosed()
    {
        Recoil::run(
            function () {
                yield $this->channel->close();
                $this->setExpectedException(ChannelClosedException::CLASS);
                yield $this->channel->read();
            }
        );
    }
}

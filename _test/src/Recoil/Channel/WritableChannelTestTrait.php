<?php

namespace Recoil\Channel;

use Recoil\Channel\Exception\ChannelClosedException;
use Recoil\Recoil;

trait WritableChannelTestTrait
{
    public function testCloseWithPendingWrite()
    {
        $output = [];

        Recoil::run(
            function () use (&$output) {
                $writer = function () use (&$output) {
                    try {
                        $output[] = 'writing';
                        yield $this->channel->write('foo');
                    } catch (ChannelClosedException $e) {
                        $output[] = 'closed';
                    }
                };

                $closer = function () use (&$output) {
                    $output[] = 'closing';
                    yield $this->channel->close();
                };

                yield Recoil::execute($writer());
                yield Recoil::execute($closer());
            }
        );

        $this->assertEquals(
            ['writing', 'closing', 'closed'],
            $output
        );
    }

    public function testWriteWhenClosed()
    {
        Recoil::run(
            function () {
                yield $this->channel->close();
                $this->setExpectedException(ChannelClosedException::CLASS);
                yield $this->channel->write('foo');
            }
        );
    }
}

<?php

namespace Recoil\Channel;

trait LoopbackChannelTestTrait
{
    public function testReadThenWrite()
    {
        $output = [];

        $reader = function () use (&$output) {
            $output[] = 'reading';
            $output[] = 'read ' . (yield $this->channel->read());
        };

        $writer = function () use (&$output) {
            $output[] = 'writing';
            yield $this->channel->write('foo');
            $output[] = 'write complete';
        };

        $this->kernel->execute($reader());
        $this->kernel->execute($writer());
        $this->kernel->eventLoop()->run();

        $this->assertContains(
            $output,
            [
                ['reading', 'writing', 'write complete', 'read foo'],
                ['reading', 'writing', 'read foo', 'write complete'],
            ]
        );
    }

    public function testWriteThenRead()
    {
        $output = [];

        $reader = function () use (&$output) {
            $output[] = 'reading';
            $output[] = 'read ' . (yield $this->channel->read());
        };

        $writer = function () use (&$output) {
            $output[] = 'writing';
            yield $this->channel->write('foo');
            $output[] = 'write complete';
        };

        $this->kernel->execute($writer());
        $this->kernel->execute($reader());
        $this->kernel->eventLoop()->run();

        $this->assertContains(
            $output,
            [
                ['writing', 'reading', 'write complete', 'read foo'],
                ['writing', 'reading', 'read foo', 'write complete'],
            ]
        );
    }
}

<?php
namespace Icecave\Recoil\Stream;

interface WritableStreamInterface
{
    public function write($buffer, $length = null);

    public function close();

    public function isClosed();
}

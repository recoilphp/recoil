<?php
namespace Icecave\Recoil\Channel\Exception;

/**
 * A common interface for grouping all channel related exceptions.
 */
interface ChannelExceptionInterface
{
    /**
     * @return ChannelInterface
     */
    public function channel();
}

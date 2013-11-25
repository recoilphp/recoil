<?php
namespace Icecave\Recoil\Channel\Exception;

use Exception;
use Icecave\Recoil\Channel\ChannelInterface;

/**
 * A read or write operation was attempted on a channel that has been closed.
 */
class ChannelClosedException extends Exception
{
    /**
     * @param ChannelInterface $channel  The channel that has been closed.
     * @param Exception|null   $previous The previous exception, if any.
     */
    public function __construct(ChannelInterface $channel, Exception $previous = null)
    {
        $this->channel = $channel;

        parent::__construct('Channel is closed.', 0, $previous);
    }

    /**
     * Fetch the channel that has been closed.
     *
     * @return ChannelInterface The channel that has been closed.
     */
    public function channel()
    {
        return $this->channel;
    }

    private $channel;
}

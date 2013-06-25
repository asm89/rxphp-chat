<?php

namespace Rx\Chat;

use Ratchet\ConnectionInterface;
use SplObjectStorage;

class Channels
{
    private $channels;

    public function get($channel)
    {
        if ( ! isset($this->channels[$channel])) {
            return new SplObjectStorage();
        }

        return $this->channels[$channel];
    }

    public function in($channel, ConnectionInterface $connection)
    {
        if ( ! isset($this->channels[$channel])) {
            return false;
        }

        return $this->channels[$channel]->contains($connection);
    }

    public function join($channel, ConnectionInterface $connection)
    {
        if ( ! isset($this->channels[$channel])) {
            $this->channels[$channel] = new SplObjectStorage();
        }

        $this->channels[$channel]->attach($connection);
    }

    public function part($channel, ConnectionInterface $connection)
    {
        if ( ! isset($this->channels[$channel])) {
            return;
        }

        $this->channels[$channel]->detach($connection);
    }

    public function partAll(ConnectionInterface $connection)
    {
        foreach ($this->channels as $channel) {
            $channel->detach($connection);
        }
    }
}

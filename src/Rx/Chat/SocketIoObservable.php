<?php

namespace Rx\Chat;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use React\EventLoop\LoopInterface;
use React\Socket\Server as Reactor;
use Rx\Observable\BaseObservable;
use Rx\Disposable\CallbackDisposable;

class SocketIoObservable extends BaseObservable implements MessageComponentInterface
{
    private $address;
    private $loop;
    private $port;

    public function __construct(LoopInterface $loop, $address = '0.0.0.0', $port = 8080)
    {
        $this->address = $address;
        $this->loop    = $loop;
        $this->port    = $port;
    }

    protected function doStart($scheduler)
    {
        $socket = new Reactor($this->loop);
        $socket->listen($this->port, $this->address);

        $ioServer = new IoServer($this, $socket, $this->loop);

        return new CallbackDisposable(function(){}); //todo: actually dispose..
    }

    private function notifyObservers($message)
    {
        foreach ($this->observers as $observer) {
            $observer->onNext($message);
        }
    }

    public function onOpen(ConnectionInterface $connection)
    {
        if (! $this->started) {
            return;
        }

        $this->notifyObservers(array('open', $connection));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        if (! $this->started) {
            return;
        }

        $this->notifyObservers(array('message', $from, $msg));
    }

    public function onClose(ConnectionInterface $conn)
    {
        if (! $this->started) {
            return;
        }

        $this->notifyObservers(array('close', $conn));
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        if (! $this->started) {
            return;
        }

        $this->notifyObservers(array('error', $conn));
    }
}

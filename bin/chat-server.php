<?php

require __DIR__ . '/../vendor/autoload.php';

/*
 * Initial setup of the react eventloop.
 */
$loop      = React\EventLoop\Factory::create();
$scheduler = new Rx\Scheduler\EventLoopScheduler($loop);

$loop->addPeriodicTimer(60, function () {
    $memory = memory_get_usage() / 1024;
    $formatted = number_format($memory, 3).'K';
    echo "Current memory usage: {$formatted}\n";
});

/*
 * Start of creating a new observable for our socket.
 */
$observable = new Rx\Chat\SocketIoObservable($loop);

/*
 * Now subscribe a callback to it that will receive all the messages from the
 * observable
 */
$observable
    ->subscribeCallback(function(array $value){ echo 'GOT: ' . $value[0] . "\n"; });

/*
 * Let's create an observable of new connections. By:
 *     - Filtering the original observable for "open" messages
 *     - Selecting the only the connection from the message
 */
$connectObservable = $observable
    ->where(function(array $elem) { return $elem[0] === 'open'; })
    ->select(function(array $elem) { return $elem[1]; });

/*
 * Now for each newly opened connection, store the connection.
 */
$clients = new \SplObjectStorage;
$connectObservable
    ->subscribeCallback(function(Ratchet\ConnectionInterface $connection) use ($clients) {
        $clients->attach($connection);
    });

/*
 * Let's do the same for closing connections, this time removing them from the
 * clients map.
 */
$closedObservable = $observable
    ->where(function(array $elem) { return $elem[0] === 'closed'; })
    ->select(function(array $elem) { return $elem[1]; });
$closedObservable
    ->subscribeCallback(function(Ratchet\ConnectionInterface $connection) use ($clients) {
        $clients->detach($connection);
    });

/*
 * Finally create an observable of messages and use it to send the received 
 * messaged to all clients.
 */
$messagesObservable = $observable
    ->where(function($elem) { return $elem[0] === 'message'; })
    ->select(function($elem) { return array($elem[1], $elem[2]); });

$messagesObservable
    ->subscribeCallback(function(array $value) use ($clients){
        list($from, $message) = $value;

        foreach ($clients as $client) {
            if ($from !== $client) {
                $client->send($message);
            }
        }
    });

$loop->run();

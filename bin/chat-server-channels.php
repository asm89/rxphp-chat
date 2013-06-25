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
 * Create an observable of messages.
 */
$messagesObservable = $observable
    ->where(function($elem) { return $elem[0] === 'message'; })
    ->select(function($elem) { return array($elem[1], $elem[2]); });

/*
 * We will now create three more observables:
 *     - One containing channels join messages (e.g. "join #channel")
 *     - One containing channels part messages (e.g. "part #channel")
 *     - One containing channels message to a channel (e.g. "#channel hi")
 */
// helper function to extract the channel from the message
function extractChannel($message) {
    if (1 !== preg_match('/#([A-Za-z0-9]+)(.*)/', $message[1], $matches)) {
        throw new RuntimeException(sprintf("No channel found in '%s'.", $message));
    }

    return array($message[0], $matches[1], trim($matches[2]));
}

$channelJoinObservable = $messagesObservable
    ->where(function($elem) { return 0 === strpos($elem[1], 'join #'); })
    ->select('extractChannel');
$channelPartObservable = $messagesObservable
    ->where(function($elem) { return 0 === strpos($elem[1], 'part #'); })
    ->select('extractChannel');
$channelMessagesObservable = $messagesObservable
    ->where(function($elem) { return 0 === strpos($elem[1], '#'); })
    ->select('extractChannel');

/*
 * Next we create a small collection object to hold subscription to channels. 
 * Checkout the source. :)
 */
$channels = new Rx\Chat\Channels();

/*
 * Use the join and part messages observables to join/part the clients.
 */
$channelJoinObservable
    ->subscribeCallback(function($elem) use ($channels) {
        list($connection, $channel) = $elem;
        $channels->join($channel, $connection);
        echo 'Someone joining #' . $channel . "\n";
    });

$channelPartObservable
    ->subscribeCallback(function($elem) use ($channels) {
        list($connection, $channel) = $elem;
        $channels->part($channel, $connection);
        echo 'Someone parting #' . $channel . "\n";
    });

/*
 * Group the messages per channel. By:
 *      - Providing a method that returns the key to group by
 *      - Subscribing to the new stream of messages only for one channel
 *      - Filter messages to have only messages from clients that are in the channel
 *      - Broadcast to all other messages
 */
$channelMessagesObservable
    ->groupBy(function($elem) {
        list($connection, $channel) = $elem;

        return $channel;
    })
    ->subscribeCallback(function(Rx\Observable\GroupedObservable $observable) use ($channels) {
        $channel = $observable->getKey();

        // Observable that will only contain messages for one channel
        $observable
            ->where(function($elem) use ($channels, $channel) { return $channels->in($channel, $elem[0]); })
            ->subscribeCallback(function ($elem) use ($channels, $channel) {
                list($from, , $message) = $elem;

                $clients = $channels->get($channel);
                foreach ($clients as $client) {
                    if ($from !== $client) {
                        $client->send('> #' . $channel . ': ' . $message . "\n");
                    }
                }
            });
    });

$loop->run();

Chat demo with Rx.PHP
=====================

This repository contains a simple demo application showing some of the
abilities of [Rx.PHP].

[Rx.PHP]: https://github.com/asm89/Rx.PHP

To run the project:

- Clone this repository
- Use [composer] to install the dependencies: `composer.phar install`
- Run the examples

[composer]: http://getcomposer.org/

The source files in the `bin/` directory are commented in a tutorial like
fashion.

## chat-server.php

`chat-server.php` contains a chat application that broadcasts messages to all
connected clients. To run:

```bash
$ php bin/chat-server.php
```

Connect to the server multiple times to see it in action.

```bash
$ telnet localhost 8080
Trying 127.0.0.1...
Connected to localhost.
Escape character is '^]'.
\o/
```

## chat-server-channels.php

`chat-server-channels.php` builds on the other version, but now extending the
functionality of the server with simple channels and commands to join, part and
send messages to channels.

Commands:

```
join #name
part #name
#name Hi all!
```

To run:

```bash
$ php bin/chat-server-channels.php
```

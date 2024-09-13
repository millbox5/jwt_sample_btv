<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require __DIR__ . '/vendor/autoload.php';

class MyWebSocketServer implements MessageComponentInterface {
    // ...
}

$server = new HttpServer(new WsServer(new MyWebSocketServer()));
$server->listen(9000, '0.0.0.0');
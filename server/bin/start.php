#!/usr/bin/php
<?php
/**
 * A websocket server, pushing messages to users
 * Received commands from the API via ZMQ
 */

use Ratchet\Http\OriginCheck;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Huhu\Server;

$pid=$argv[1];
if (preg_match('/^[\/a-zA-Z0-9\.]*/', $pid)) {
  file_put_contents($pid, getmypid());
}


// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
  realpath(dirname(__DIR__) . '/../library'),
  realpath(dirname(__DIR__) . '/../library/zendframework/zendframework1/library'),
)));

require dirname(__DIR__) . '/../vendor/autoload.php';

$server=new Huhu\Server;

$loop = React\EventLoop\Factory::create();

// listen for API data (ZeroMQ http://socketo.me/docs/push)
$context = new React\ZMQ\Context($loop);
$pull = $context->getSocket(ZMQ::SOCKET_PULL);
$pull->bind('tcp://127.0.0.1:5555');
$pull->on('error', function ($e) {
  var_dump($e->getMessage());
});
$pull->on('message', Array($server, 'onAPICommand'));


$webSock=new React\Socket\Server($loop);
$webSock->listen(8410, '127.0.0.1');
//$webSock->listen(8410, '0.0.0.0');

$webServer=new Ratchet\Server\IoServer(
  new Ratchet\Http\HttpServer(
//    new Ratchet\Http\OriginCheck(
      new Ratchet\WebSocket\WsServer(
        $server
    )
  ),
  $webSock
);



$loop->run();
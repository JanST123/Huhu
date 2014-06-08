#!/usr/bin/php
<?php
/**
 * A serverscript for pushing APN messages
 * receive command from the API via ZMQ
 */
// Report all PHP errors
error_reporting(-1);

use APN\Pusher;


// Using Autoload all classes are loaded on-demand
require_once dirname(__FILE__) . '/../src/ApnsPHP/Autoload.php';
require dirname(__DIR__) . '/../vendor/autoload.php';


// write pid file
$pid = $argv[1];
if (preg_match('/^[\/a-zA-Z0-9\.]+/', $pid)) {
    file_put_contents($pid, getmypid());
}

$pusher = new APN_Pusher();


// listen for API data (ZeroMQ http://socketo.me/docs/push)
$loop = React\EventLoop\Factory::create();

$context = new React\ZMQ\Context($loop);
$pull = $context->getSocket(ZMQ::SOCKET_PULL);
$pull->bind('tcp://127.0.0.1:6666');
$pull->on('error', Array($pusher, 'onZMQError'));
$pull->on('message', Array($pusher, 'onApiMessage'));

$pusher->initEventLoop($loop);

$loop->run();




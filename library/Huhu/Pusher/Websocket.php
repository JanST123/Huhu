<?php
/**
 * Contains the Pusher\Websocket class
 */

namespace Huhu\Library\Pusher;

/**
 * Class Websocket
 * Push via our own websocket server
 *
 * We create this pusher instance for a user we want to push TO(!!)
 * So we can have multiple instances of this, if we want to multiple users (which is the common case)
 */
class Websocket extends \Huhu\Library\Pusher implements \Huhu\Library\Pusher\PusherInterface {
  /**
   * @var string the encryption key
   */
  public static $key='afd888caacc118899feeddfee898abba';

  /**
   * @var string a token, generated by this API (UserController > pushauthGenerateWebsocketTokenAction() ) identifying the user on the websocket server
   */
  private $_token;
  /**
   * @var Int our internal user ID, to who this Pusher instance belongs
   */
  private $_userId;


  /**
   * Instanciates a new pusher for the user we want to push to. Fetches the token from the database
   * @param int $userId the ID of the user we want to push TO
   */
  public function __construct($userId) {
    $this->_userId=$userId;
    $pushMethods=\Huhu\Library\Push::getUserMethods($this->_userId);

    if (isset($pushMethods['websocket'])) {
      $this->_token=$pushMethods['websocket']['token'];

      // check if we should request a renewal of token
      $diff=strtotime($pushMethods['websocket']['valid_until']) - time();
      if ($diff < 3600) {
        $this->push(self::assembleRevalidate());
      }
    }
  }


  /**
   * Push data to the user
   * @param Array $data Data which should be pushed to the client. Should be assembled thru the @see \Huhu\Library\Pusher assembleXXX Methods
   */
  public function push($data) {
    if (empty($this->_token)) return; // no token, no push

    $loop = \React\EventLoop\Factory::create();

    $context = new \React\ZMQ\Context($loop);

    $push = $context->getSocket(\ZMQ::SOCKET_PUSH);
    $push->connect('tcp://127.0.0.1:5555');

    $push->send(
      serialize(
        Array(
          'action' => 'send',
          'receiver' => $this->_token,
          'data' => $data,
        )
      )
    );

    $loop->run();
  }

}
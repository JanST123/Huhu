<?php
/**
 * The Websocket Server class
 */

namespace Huhu;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

// Define path to application directory
defined('APPLICATION_PATH')
|| define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../../../application'));

// Define application environment
defined('APPLICATION_ENV')
|| define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));


/**
 * Class Server
 * @package Huhu
 *
 * The Websocket Server class
 */
class Server implements MessageComponentInterface {

  /**
   * @var \SplObjectStorage stores the connected clients
   */
  protected $clients;
  /**
   * @var array stores tokens for the clients
   */
  protected $tokens;

  /**
   * @var string key for encrypting/decrypting data
   */
  private $key='afd888caacc118899feeddfee898abba';

  public function __construct() {
    $this->clients=new \SplObjectStorage;
  }

  /**
   * Received command from API via ZMQ
   * @param $data
   */
  public function onAPICommand($data) {
    $this->_debug('API COMMAND: '.$data);
    $data=unserialize($data);

    if (is_array($data) && isset($data['action'])) {
      switch ($data['action']) {
        case 'send':
          if (isset($data['receiver'])) {
            $token=$data['receiver'];

            $this->_debug('Send message to '.$token);


            if (isset($this->tokens[$token])) {
              $this->_debug('Receiver found');

              // we found the connection obj
              $this->tokens[$token]->send(json_encode($data['data']));
            }
          }
          break;

        default:
          $this->_debug('Unknown Action '.$data['action']);
          break;
      }
    }
  }


  /**
   * Called if user connects to websocket server
   * @param ConnectionInterface $conn
   */
  public function onOpen(ConnectionInterface $conn) {
    $this->clients->attach($conn);

    $this->_debug('New Connection '.$conn->resourceId);
  }

  /**
   * called if message from websocket client received
   * @param ConnectionInterface $from
   * @param string $msg
   * @throws \Zend_Db_Exception
   */
  public function onMessage(ConnectionInterface $from, $msg) {
    $this->_debug('Message from '.$from->resourceId.' Message: '.$msg);

    $msg=json_decode($msg, true);

    if (is_array($msg)) {
      if (isset($msg['action'])) {
        switch ($msg['action']) {
          case 'auth':
            // authenticate client on websocket server
            if (isset($msg['token']) && !empty($msg['token'])) {
              // check if token exists in db and is valid

              $tokenDecoded=mcrypt_decrypt('cast-128', pack('H*', $this->key), base64_decode($msg['token']), MCRYPT_MODE_CFB, str_pad('0', 8));

              $config=new \Zend_Config_Ini(APPLICATION_PATH.'/configs/application.ini', APPLICATION_ENV);
              $db=\Zend_Db::factory($config->database);
              $stmt=$db->prepare("SELECT `valid_until` FROM user_push_auth WHERE token=?");
              if ($stmt->execute(Array($tokenDecoded))) {
                if ($stmt->rowCount()) {
                  $row=$stmt->fetch(\Zend_Db::FETCH_ASSOC);
                  if ($row['valid_until'] > date('Y-m-d H:i:s')) {

                    $this->_debug('Valid token');

                    // save association token->object to the tokens array
                    $this->tokens[$tokenDecoded]=$from;
                  } else {
                    // trigger revalidate
                    $this->_debug('Token expired, requesting revalidate');

                    $from->send(json_encode(Array('action' => 'revalidate')));
                  }
                } else {
                  $this->_debug('Invalid token.');

                  $from->send(json_encode(Array('action' => 'revalidate')));
                }
              }
            }
            break;

          default:
            $this->_debug('Unkown action from client received '.print_r($msg, 1));
            break;
        }
      }
    }
  }

  /**
   * Called if websocket connection is closed
   * @param ConnectionInterface $conn
   */
  public function onClose(ConnectionInterface $conn) {
    if (is_array($this->tokens)) {
      foreach ($this->tokens as $k => $v) {
        if ($v->resourceId==$conn->resourceId) {
          $this->_debug('Connection from TOKEN '.$k.' closed and removed');
          unset($this->tokens[$k]);
          break;
        }
      }
    }

    $this->clients->detach($conn);

    $this->_debug('Connection from '.$conn->resourceId.' closed');
  }


  /**
   * Called if error occured
   * @param ConnectionInterface $conn
   * @param \Exception $e
   */
  public function onError(ConnectionInterface $conn, \Exception $e) {
    $this->_debug('ERROR: for '.$conn->resourceId.' Message: '.$e->getMessage());
  }

  /**
   * debug :)
   * @param $text
   */
  private function _debug($text) {
//    file_put_contents('/tmp/huhu_server.log', "\n".date('d.m.Y H:i:s')." ".$text);
//    echo "\n".date('d.m.Y H:i:s')." ".$text;
  }
}
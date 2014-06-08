<?php
/**
 * Contains the Pusher class
 */

namespace Huhu\Library;

/**
 * Class \Huhu\Library\Pusher
 * The base class of the different pusher classes (APN, GCM, Websocket)
 */
class Pusher {
  /**
   * Creates a new pusher instance depending on the transport Type (Apn, Gcm, or Websocket)
   *
   * @param string $transportType The Transport type (Apn, Gcm or Websocket)
   * @param int $userId
   * @return mixed
   * @throws Exception
   */
  public static function factory($transportType, $userId) {
    $classname='\\Huhu\\Library\\Pusher\\'.ucfirst($transportType);
    if (class_exists($classname)) {
      return new $classname($userId);
    } else {
      throw new Exception('Transport type not defined');
    }
  }


  /**
   * Message type 'revalidate' triggers revalidation of push token on app
   * @return string
   */
  public static function assembleRevalidate() {
    return (Array('action' => 'revalidate'));
  }

  /**
   * Message type 'openchats' triggers reload of open chats
   * @param $shortMessage
   * @return string
   */
  public static function assembleOpenChat($shortMessage) {
    return (Array('action' => 'openchats', 'message' => $shortMessage));
  }


  /**
   * Message type 'updatepublickey' triggers reload of the public key for all friend users
   * @return array
   */
  public static function assemblePublicKeyChanged() {
    return (Array('action' => 'updatepublickey'));
  }

  /**
   * Message type 'chatclosed' triggers the close of a chat window on clients
   * @param $chatId
   * @return string
   */
  public static function assembleClosedChat($chatId) {
    return (Array('action' => 'chatclosed', 'chatId' => $chatId));
  }


  /**
   * Message type 'message' pushes message to a chat
   * @param string $shortMessage
   * @param int $chatId
   * @param string $fullmessage
   * @param string $from
   * @param bool $mySelf
   * @param int $messageId
   * @return array $message
   */
  public static function assembleChatMessage($shortMessage, $chatId, $fullmessage, $from, $mySelf, $messageId) {
    return (
      Array(
        'action' => 'message',
        'message' => $shortMessage,
        'chatId' => $chatId,
        'fullmessage' => $fullmessage,
        'dateTime' => \Huhu\Library\Date::getSmartDate(time()),
        'fromuser' => $from,
        'mySelf' => $mySelf,
        'message_id' => $messageId,
      )
    );
  }


  /**
   * Message type 'contactrequest', triggers reload of contactlist on app
   * @param $shortMessage
   * @return array contactRequest
   */
  public static function assembleContactRequest($shortMessage) {
    return Array('action' => 'contactrequest', 'message' => $shortMessage);
  }


  /**
   * Message type 'userlist' triggers reload of userlist in open chat
   * @return array
   */
  public static function assembleUserlist() {
    return Array('action' => 'userlist');
  }


  /**
   * Message type 'contactlist' triggers reload of contact list
   * @return array
   */
  public static function assembleContactlist() {
    return Array('action' => 'contactlist');
  }

}
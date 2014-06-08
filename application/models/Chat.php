<?php
/**
 * Contains the ChatModel class
 */

/**
 * Class Application_Model_Chat
 *
 * Contains all the data for one chat
 * @property int $id The unique ID of the chat
 * @method int getId() returns the unique ID of the chat
 * @method void setId(integer $id) sets the unique ID of the chat
 *
 * @property string $timestamp The mySQL-Timestamp when this chat was created
 * @method string getTimestamp() returns the mySQL-Timestamp when this chat was created
 * @method void setTimestamp(string $timestamp) sets the mySQL-Timestamp when this chat was created
 *
 * @property int $fk_ownerUserID The User-ID of the chat-owner
 * @method int getFk_ownerUserId() returns the User-ID of the chat-owner
 * @method void setFk_ownerUserId(int $fk_ownerUserID) sets the User-ID of the chat-owner
 *
 */
class Application_Model_Chat extends \Huhu\Library\Model\Base {

  /**
   * @var string The database Table
   */
  protected $_table='chats';

  /**
   * @var array Contains the fields, available in the database table.
   * DON'T FORGET TO ADD NEW FIELDS as "@property" and the getter/setter as "@method" TO THE CLASS DOC-BLOCK!!
   */
  protected $_properties=Array(
    'id' => null,
    'timestamp' => null,
    'fk_ownerUserID' => null,
  );




}
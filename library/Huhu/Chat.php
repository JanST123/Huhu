<?php
/**
 * Contains the Chat class
 */

namespace Huhu\Library;

/**
 * Class \Huhu\Library\Chat
 * Contains helper methods for chats
 */
class Chat
{
  /**
   * Generates a unique continuous message id
   * @param int $chatId The ID of the chat, for which the message should be created
   * @return string $newId The new Chat ID
   */
  public static function generateMessageID($chatId)
  {
    $mc = \Zend_Registry::get('Zend_Cache');

    $lastId = $mc->load(\Huhu\Library\MemcacheManager::getKeyChatMessageId($chatId));

    if ($lastId === false) {
      // fetch last id from database

      $db = \Zend_Registry::get('Zend_Db');
      $res = $db->query(
        "SELECT message_id FROM chats_messages WHERE fk_chatID = " . (int)$chatId . " ORDER BY message_id DESC LIMIT 1"
      );
      $row = $res->fetch(\Zend_DB::FETCH_ASSOC);
      $lastId = $row['message_id'];
    }
    if (empty($lastId)) {
      $lastId = '0';
    }

    if (empty($lastId)) {
      $lastId = $chatId . '_0';
    }
    $lastIdWithoutPrefix = explode('_', $lastId);
    $lastIdWithoutPrefix = $lastIdWithoutPrefix[1];

    $newId = base_convert($lastIdWithoutPrefix, 36, 10);
    ++$newId;
    $newId = base_convert($newId, 10, 36);
    $newId = $chatId . '_' . $newId;

    $mc->save($newId, \Huhu\Library\MemcacheManager::getKeyChatMessageId($chatId));

    return $newId;


  }

  /**
   * returns new messages in the chat (from memcache),
   * if chat not exists in memcache, chat will be created, and messages (if any) from DB will be put to memcache
   * @param int $chatId The ID of the Chat for which the messages should be fetched
   * @param string $lastMessageId Optional message-ID of the last fetched message. If provided, only messages with ID > that ID will be returned
   * @param bool $onlyFromCache Do not query Database for messages. Messages which are not in the Cache were not returned
   * @param bool $onlyForCurrentUser Only return messages which were sent to the current logged in User
   * @return Array $messages
   */
  public static function getMessages(
    $chatId,
    $lastMessageId = null,
    $onlyFromCache = false,
    $onlyForCurrentUser = false
  ) {
    $mc = \Zend_Registry::get('Zend_Cache');

    $mcChat = $mc->load(\Huhu\Library\MemcacheManager::getKeyChat($chatId));

    if ($onlyFromCache) {
      if (!is_array($mcChat)) $mcChat=Array();
      return $mcChat;
    }

    $db = \Zend_Registry::get('Zend_Db');
    $currentUser=null;
    if ($onlyForCurrentUser) {
	    $currentUser = \Zend_Registry::get('loggedinuser');
	}

    if ($mcChat === false || !is_array($mcChat)) {
      // load messages from db, create chat in db

      $maxMessagesInStore = (int)\Zend_Registry::get('Zend_Config')->chat->maxMessagesInStore;

      $res = $db->query(
        "SELECT m.message_id, m.message, m.fk_recipientUserID AS recipient_id, m.timestamp, u.user AS user_name, u.id AS user_id FROM chats_messages AS m
                       LEFT JOIN users AS u ON u.id = m.fk_userID
                       WHERE m.fk_chatID = " . (int)$chatId . " ORDER BY m.message_id DESC LIMIT " . $maxMessagesInStore
      );
      if ($res) {
        $rows = $res->fetchAll(\Zend_Db::FETCH_ASSOC);
        $mc->save($rows, \Huhu\Library\MemcacheManager::getKeyChat($chatId));
        $mcChat = $rows;
      }
    }


    foreach ($mcChat as $k => $v) {
      $mcChat[$k]['datetime'] = \Huhu\Library\Date::getSmartDate(strtotime($v['timestamp']));
      $mcChat[$k]['dateTime'] = $mcChat[$k]['datetime'];

      $mcChat[$k]['myself'] = 0;
      if ($currentUser && $v['user_id'] == $currentUser['id']) {
        $mcChat[$k]['myself'] = 1;
      }
    }

    $return = Array();


    $lastMessageIdNum = 0;
    if ($lastMessageId) {
      $lastMessageIdNum = explode('_', $lastMessageId);
      $lastMessageIdNum = base_convert($lastMessageIdNum[1], 36, 10);
    }

    foreach ($mcChat as $msg) {

      $msgIdNum = 0;
      if ($lastMessageId) {
        $msgIdNum = explode('_', $msg['message_id']);
        $msgIdNum = base_convert($msgIdNum[1], 36, 10);
      }


      if ($lastMessageId === null || $msgIdNum > $lastMessageIdNum) {
        if (!$onlyForCurrentUser || ($currentUser && $msg['recipient_id'] == $currentUser['id'])) {
          $return[] = $msg;
        }
      }
    }

    return $return;

  }


  /**
   * Adds message to a chat, cleans up so that maximum $_maxMessagesInStore messages are stored
   * If chat does not exists in memcache, it will be created (and messages from db (if any) will be stored in memcache)
   * @param int $chatId The ID of the chat where to add the message
   * @param int $userid The User-ID of the sender
   * @param Array $messages (recipient=>encodedMessage)
   * @param String $messageId (unique message id)
   * @return bool $success
   */
  public static function addMessage($chatId, $userid, $messages, $messageId)
  {
    $mc = \Zend_Registry::get('Zend_Cache');
    $db = \Zend_Registry::get('Zend_Db');

    $mcChat = $mc->load(\Huhu\Library\MemcacheManager::getKeyChat($chatId));
    if ($mcChat === false) {
      // load messages from db, create chat in db

      $res = $db->query(
        "SELECT m.message_id, m.message, m.timestamp, m.fk_recipientUserID AS recipient_id, u.user AS user_name, u.id AS user_id FROM chats_messages AS m
                  LEFT JOIN users AS u ON u.id = m.fk_userID
                  WHERE m.fk_chatID = " . (int)$chatId . " ORDER BY m.id DESC LIMIT " . ((int)\Zend_Registry::get(
            'Zend_Config'
          )->chat->maxMessagesInStore - 1)
      );
      if ($res) {
        $rows = $res->fetchAll(\Zend_Db::FETCH_ASSOC);
        $mcChat = $rows;
      }
    }

    foreach ($messages as $recipientId => $message) {
      $mcChat[] = Array(
        'message' => $message,
        'recipient_id' => $recipientId,
        'timestamp' => date('Y-m-d H:i:s', time()),
        'user_name' => \Huhu\Library\User::getUserName($userid),
        'user_id' => $userid,
        'message_id' => $messageId,
      );

      // is the current active chat of this user the one we are pushing into?
      $activeChat = $mc->load(\Huhu\Library\MemcacheManager::getKeyActiveChat($recipientId));
      $userstatus = $mc->load(\Huhu\Library\MemcacheManager::getKeyUserLastOnlineStatus($recipientId));
      if ($activeChat == $chatId && $userstatus == \Huhu\Library\User::$USER_STATUS_ONLINE) {
        // user is online and has this chat open. Update last read timestamp

        $mc->save($messageId, \Huhu\Library\MemcacheManager::getKeyChatLastRead($recipientId, $chatId));
      }
    }


    // save back to memcache
    $mc->save($mcChat, \Huhu\Library\MemcacheManager::getKeyChat($chatId));

    \Huhu\Library\MemcacheManager::invalidateOnMessagePush($chatId);
    return true;
  }


  /**
   * Opens a new chat with the given user IDs
   * @param array $userids
   * @return int $chatid
   */
  public static function open($userids)
  {
    $currentUser = \Zend_Registry::get('loggedinuser');
    $db = \Zend_Registry::get('Zend_Db');

    if (!is_array($userids)) {
      $userids = Array($userids);
    }


    // check if contact was accepted from each userid...
    foreach ($userids as $userId) {
      $res = $db->query(
        "SELECT accepted FROM contactlist WHERE fk_ownerUserID = " . (int)$currentUser['id'] . " AND fk_contactUserID=" . (int)$userId
      );
      if ($res) {
        $row = $res->fetch(\Zend_Db::FETCH_ASSOC);
        if (!$row['accepted']) {
          return false;
        }
      }
    }


    // insert to db
    $chatId = null;
    if ($db->query(
      "INSERT INTO chats (timestamp, fk_ownerUserID) VALUES (CURRENT_TIMESTAMP(), " . (int)$currentUser['id'] . ")"
    )
    ) {
      $chatId = $db->lastInsertId();

      $insertQuery = "INSERT INTO chats_user (fk_chatID, fk_userID) VALUES (" . (int)$chatId . ", " . (int)$currentUser['id'] . ");\n";
      foreach ($userids as $userid) {
        // check if userid is on contactlist of current user and is accepted, than add to chats_user
        $res = $db->query(
          "SELECT accepted FROM contactlist WHERE fk_ownerUserID = " . (int)$currentUser['id'] . " AND fk_contactUserID=" . (int)$userid
        );
        $row = $res->fetch(\Zend_Db::FETCH_ASSOC);
        if ($row['accepted'] == 1) {
          $insertQuery .= "INSERT INTO chats_user (fk_chatID, fk_userID) VALUES (" . (int)$chatId . ", " . (int)$userid . ");\n";
        }
      }

      if (!empty($insertQuery) && $db->query($insertQuery)) {
        \Huhu\Library\MemcacheManager::invalidateOnChatUsersChange($chatId);

        // call this to fillup memcache again
        self::getOpen(true);

        return $chatId;

      }
    }
    return false;
  }


  /**
   * Reopens an existing chat
   * @param int $chatId
   * @return array
   */
  public static function reopen($chatId)
  {
    $currentUser = \Zend_Registry::get('loggedinuser');
    $db = \Zend_Registry::get('Zend_Db');
    $mc = \Zend_Registry::get('Zend_Cache');
    $translate = \Zend_Registry::get('Zend_Translate');

    // check if exists and get users
    $stmt = $db->prepare(
      "SELECT u.id AS user_id, u.user FROM chats AS c
              LEFT JOIN chats_user AS cu ON cu.fk_chatID = c.id
              LEFT JOIN users AS u ON u.id = cu.fk_userID
              WHERE c.id=?"
    );
    if ($stmt->execute(Array($chatId)) && $stmt->rowCount()) {
      $name = $translate->_('Chat with').' ';
      $users = Array();
      $rows = $stmt->fetchAll(\Zend_Db::FETCH_ASSOC);

      // usernames into chat title
      $usersNotMe = 0;
      foreach ($rows as $row) {
        if ($row['user_id'] != $currentUser['id']) {
          if ($usersNotMe) {
            $name .= ', ';
          }
          $name .= $row['user'];

          ++$usersNotMe;
        }
        $users[$row['user_id']] = $row['user'];
      }

      $title = $name;
      // userpictures into chat title
      foreach ($rows as $row) {
        if ($row['user_id'] != $currentUser['id']) {
          $title .= '&nbsp;' . \Huhu\Library\User::getUserPicture($row['user_id'], false, true);
        }
      }

      $messages = \Huhu\Library\Chat::getMessages($chatId, null, true, true);
      $lastMessageId = null;
      foreach ($messages as $m) {
        if ($m['message_id'] > $lastMessageId) {
          $lastMessageId = $m['message_id'];
        }
      }

      // update the last open timestamp in db
      $db->query(
        "UPDATE chats_user SET last_read_message_id=" . $db->quote(
          $lastMessageId
        ) . " WHERE fk_chatID=" . (int)$chatId . " AND fk_userID=" . (int)$currentUser['id']
      );
      $mc->save($lastMessageId, \Huhu\Library\MemcacheManager::getKeyChatLastRead($currentUser['id'], $lastMessageId));

      \Huhu\Library\MemcacheManager::invalidateOnChatUsersChange($chatId);


      return Array(
        'name' => $name,
        'title' => $title,
        'users' => $users,
        'messages' => $messages,
      );
    }

    return false;
  }


  /**
   * Adds userids to existing chats
   * @param int $chatId
   * @param array $userids
   * @return Array $users
   */
  public static function addUser($chatId, $userids)
  {
    $currentUser = \Zend_Registry::get('loggedinuser');
    $db = \Zend_Registry::get('Zend_Db');

    if (!is_array($userids)) {
      $userids = Array($userids);
    }

    $insertQuery = '';
    foreach ($userids as $userid) {
      // check if userid is on contactlist of current user and is accepted, than add to chats_user
      $res = $db->query(
        "SELECT accepted FROM contactlist WHERE fk_ownerUserID = " . (int)$currentUser['id'] . " AND fk_contactUserID=" . (int)$userid
      );
      $row = $res->fetch(\Zend_Db::FETCH_ASSOC);
      if ($row['accepted'] == 1) {
        $insertQuery .= "INSERT IGNORE INTO chats_user (fk_chatID, fk_userID) VALUES (" . (int)$chatId . ", " . (int)$userid . ");\n";
      }
    }

    if (!empty($insertQuery) && $db->query($insertQuery)) {
      \Huhu\Library\MemcacheManager::invalidateOnChatUsersChange($chatId);

      // get all users and return
      $stmt = $db->prepare(
        "SELECT u.id AS user_id, u.user FROM chats_user AS cu
                            LEFT JOIN users AS u ON u.id = cu.fk_userID
                            WHERE cu.fk_chatID=?"
      );
      if ($stmt->execute(Array($chatId))) {
        $users = Array();
        $rows = $stmt->fetchAll(\Zend_Db::FETCH_ASSOC);
        foreach ($rows as $row) {
          $users[$row['user_id']] = $row['user'];
        }

        return $users;
      }
    }
    return false;
  }


  /**
   * Saves the currently opened chat to memcache
   * @param int $chatId
   * @return bool
   */
  public static function setActiveChat($chatId)
  {
    $db = \Zend_Registry::get('Zend_Db');
    $mc = \Zend_Registry::get('Zend_Cache');
    $currentUser = \Zend_Registry::get('loggedinuser');

    // last opened chat... (update last_read_message_id)


    if ($chatId) {
      $messages = \Huhu\Library\Chat::getMessages($chatId);
      $lastMessageId = null;
      foreach ($messages as $m) {
        if ($m['message_id'] > $lastMessageId) {
          $lastMessageId = $m['message_id'];
        }
      }
      $db->query(
        "UPDATE chats_user SET last_read_message_id = " . $db->quote(
          $lastMessageId
        ) . " WHERE fk_chatID=" . (int)$chatId . " AND fk_userID=" . (int)$currentUser['id']
      );
      $mc->save($lastMessageId, \Huhu\Library\MemcacheManager::getKeyChatLastRead($currentUser['id'], $chatId));
    }

    $mc->save($chatId, \Huhu\Library\MemcacheManager::getKeyActiveChat($currentUser['id']));

    $mc->remove(\Huhu\Library\MemcacheManager::getKeyOpenChats($currentUser['id']));

    return true;
  }


  /**
   * Closes chat
   * @param int $chatId
   * @param bool|pointer $completelyClosed
   * @return boolean success
   */
  public static function close($chatId, &$completelyClosed)
  {
    $currentUser = \Zend_Registry::get('loggedinuser');

    $db = \Zend_Registry::get('Zend_Db');

    // delete my entry
    if ($db->query(
      "DELETE FROM chats_user WHERE fk_chatID=" . (int)$chatId . " AND fk_userID=" . (int)$currentUser['id']
    )
    ) {
      $stmt = $db->prepare("SELECT fk_userID FROM chats_user WHERE fk_chatID=?");
      $stmt->execute(Array($chatId));
      if ($stmt->rowCount() < 2) {
        // there is max 1 user left... delete the chat
        $db->query("DELETE FROM chats WHERE id=" . (int)$chatId);

        $completelyClosed = true;
      } else {
        $completelyClosed = false;
      }
      $stmt->closeCursor();

      \Huhu\Library\MemcacheManager::invalidateOnChatUsersChange($chatId);


      return true;
    }

    return false;
  }


  /**
   * Checks wether a user is in the given chat or not
   * @param int $userid
   * @param int $chatid
   * @return Bool
   */
  public static function userIsInChat($userid, $chatid)
  {
    $mc = \Zend_Registry::get('Zend_Cache');
    // Attention!! We load a "foreign" memcache dataset (not of the current user)!! (we do not write it back, if not found)
    $chats = $mc->load(\Huhu\Library\MemcacheManager::getKeyOpenChats($userid));

    if (false === $chats) {
      $chats = Array();
      $db = \Zend_Registry::get('Zend_Db');

      $stmt = $db->prepare(
        "SELECT cu.fk_chatID AS id, cu.fk_userID AS user_id
                        FROM chats_user AS cu
                        WHERE cu.fk_userID = ?"
      );
      if ($stmt->execute(Array($userid))) {
        $rows = $stmt->fetchAll(\Zend_Db::FETCH_ASSOC);
        foreach ($rows as $row) {
          $chats[$row['id']] = $row['user_id'];
        }
      }
    }

    if (array_key_exists($chatid, $chats)) {
      return true;
    }
    return false;
  }


  /**
   * returns all users in chat...
   * @param int $chatid
   * @return array $users
   */
  public static function getUsersInChat($chatid)
  {
    $db = \Zend_Registry::get('Zend_Db');
    $res = $db->query("SELECT fk_userID FROM chats_user WHERE fk_chatID=" . (int)$chatid);
    $rows = $res->fetchAll(\Zend_Db::FETCH_ASSOC);

    $return = Array();
    foreach ($rows as $row) {
      $return[] = $row['fk_userID'];
    }

    return $return;
  }


  /**
   * returns all users in chat...
   * @param int $chatid
   * @return array users
   */
  public static function getUsersInChatWithPublicKeys($chatid)
  {
    $db = \Zend_Registry::get('Zend_Db');
    $res = $db->query(
      "SELECT cu.fk_userID, UNCOMPRESS(u.public_key) AS public_key FROM chats_user AS cu
                           INNER JOIN users AS u ON u.id = cu.fk_userID
                           WHERE cu.fk_chatID=" . (int)$chatid
    );
    $rows = $res->fetchAll(\Zend_Db::FETCH_ASSOC);

    $return = Array();


    foreach ($rows as $row) {
      if (!empty($row['public_key'])) {
        $return[$row['fk_userID']] = $row['public_key'];
      }
    }

    return $return;
  }

  /**
   * Returns open chats
   * @param bool $withoutMessages do not fetch the messages
   * @param int $userId
   * @return array
   */
  public static function getOpen($withoutMessages = false, $userId = null)
  {
    $mc = \Zend_Registry::get('Zend_Cache');
    $db = \Zend_Registry::get('Zend_Db');
    $translate = \Zend_Registry::get('Zend_Translate');
    $currentUser = \Zend_Registry::get('loggedinuser');

    if ($userId === null) {
      $userId = $currentUser['id'];
    }

    $chats = $mc->load(\Huhu\Library\MemcacheManager::getKeyOpenChats($userId));

    if (false === $chats) {
      $chats = Array();

      $stmt = $db->prepare(
        "SELECT cu.fk_chatID AS id, u.user AS user_name, u.id AS user_id, cu.last_read_message_id, u.photo FROM chats_user AS cu
                            LEFT JOIN chats_user AS cu2 ON cu2.fk_chatID = cu.fk_chatID
                            INNER JOIN users AS u ON u.id = cu2.fk_userID
                            WHERE cu.fk_userID = ?"
      );
      if ($stmt->execute(Array($userId))) {
        $rows = $stmt->fetchAll(\Zend_Db::FETCH_ASSOC);

        // group rows to chatroom information
        foreach ($rows as $row) {
          if (!array_key_exists($row['id'], $chats)) {
            $lastReadMessageId = $mc->load(\Huhu\Library\MemcacheManager::getKeyChatLastRead($userId, $row['id']));
            if (!$lastReadMessageId) {
              $lastReadMessageId = $row['last_read_message_id'];
              $mc->save($lastReadMessageId, \Huhu\Library\MemcacheManager::getKeyChatLastRead($userId, $row['id']));
            }

            $unreadMessages = ($withoutMessages ? Array() : \Huhu\Library\Chat::getMessages(
              $row['id'],
              $lastReadMessageId,
              true,
              true
            ));


            $chats[$row['id']] = Array(
              'id' => $row['id'],
              'chatid' => $row['id'],
              'name' => $translate->_('Chat with').' ',
              'userPhoto' => '',
              'usersNotMe' => 0,
              'lastUnreadMsg' => '',
              'users' => Array(),
              'unreadMessages' => $unreadMessages,
              'unreadMessagesCount' => 0,
              'unreadMessagesHide' => '',
            );


          }

          if (!isset($chats[$row['id']]['users'][$row['user_id']])) {
            $chats[$row['id']]['users'][$row['user_id']] = $row['user_name'];

            if ($row['user_id'] != $userId) {
              if ($chats[$row['id']]['usersNotMe']) {
                $chats[$row['id']]['name'] .= ', ';
                $chats[$row['id']]['userPhoto'] .= '&nbsp;';
              }
              $chats[$row['id']]['name'] .= $row['user_name'];

              $chats[$row['id']]['userPhoto'] .= \Huhu\Library\User::getUserPicture($row['user_id'], false, true);

              ++$chats[$row['id']]['usersNotMe'];
            }
          }
        }
        $mc->save($chats, \Huhu\Library\MemcacheManager::getKeyOpenChats($userId));
      }
    } else {
      // chats came from memcache, we need to fetch unread messages and user status
      foreach ($chats as $k => $chat) {
        $lastReadMessageId = $mc->load(\Huhu\Library\MemcacheManager::getKeyChatLastRead($userId, $chat['id']));
        if (false === $lastReadMessageId) {
          // fetch from db
          $res = $db->query(
            "SELECT last_read_message_id FROM chats_user WHERE fk_userID=" . (int)$userId . " AND fk_chatID=" . (int)$chat['id']
          );
          if ($res) {
            $row = $res->fetch(\Zend_Db::FETCH_ASSOC);
            $lastReadMessageId = $row['last_read_message_id'];
            $mc->save($lastReadMessageId, \Huhu\Library\MemcacheManager::getKeyChatLastRead($userId, $chat['id']));
          }
        }

        if ($withoutMessages) {
          $chats[$k]['unreadMessages'] = Array();
        } else {
          $chats[$k]['unreadMessages'] = \Huhu\Library\Chat::getMessages($chat['id'], $lastReadMessageId, true, true);
        }


        $chats[$k]['userPhoto'] = '';
        $usersNotMe = 0;
        foreach ($chat['users'] as $userid => $username) {
          if ($userid != $userId) {
            if ($usersNotMe) {
              $chats[$k]['userPhoto'] .= '&nbsp;';
            }
            $chats[$k]['userPhoto'] .= \Huhu\Library\User::getUserPicture($userid, false, true);
            ++$usersNotMe;
          }
        }

      }
    }


    foreach ($chats as $k => $chat) {
      // count messages, set some flags for each chat
      $msgCount = 0;

      $lastUnreadMsg = null;
      for ($i = 0; $i < count($chats[$k]['unreadMessages']); $i++) {
        if ($chats[$k]['unreadMessages'][$i]['user_id'] != $userId) {
          $lastUnreadMsg = $chats[$k]['unreadMessages'][$i]['message'];
          $msgCount++;
        }
      }

      $chats[$k]['unreadMessagesCount'] = $msgCount;
      if ($msgCount) {
        $chats[$k]['lastUnreadMsg'] = $lastUnreadMsg;
        $chats[$k]['unreadMessagesHide'] = '';
      } else {
        $chats[$k]['unreadMessagesHide'] = 'display: none;';
      }
    }


    return $chats;
  }


  /**
   * Sums up the count of all unread messages in all chats we are in
   * @param int $userId
   * @return int count
   */
  public static function getUnreadMessageCount($userId)
  {
    $mc = \Zend_Registry::get('Zend_Cache');

    $chats = $mc->load(\Huhu\Library\MemcacheManager::getKeyOpenChats($userId));
    if (false === $chats) {
      self::getOpen(true, $userId);
      $chats = $mc->load(\Huhu\Library\MemcacheManager::getKeyOpenChats($userId));
    }

    $count = 0;


    if (is_array($chats)) {
      foreach ($chats as $chat) {
        $lastReadMessageId = $mc->load(\Huhu\Library\MemcacheManager::getKeyChatLastRead($userId, $chat['id']));


        if (!empty($lastReadMessageId)) {
          $unreadMessages = \Huhu\Library\Chat::getMessages($chat['id'], $lastReadMessageId);

          foreach ($unreadMessages as $m) {
            if ($m['recipient_id'] == $userId) {
              ++$count;
            }
          }
        }
      }
    }

    return $count;
  }


  /**
   * Returns comma separated string with senders of unread messages
   * @param int $userId
   * @return string senders
   */
  public static function getUnreadMessageSenders($userId)
  {
    $mc = \Zend_Registry::get('Zend_Cache');

    $chats = $mc->load(\Huhu\Library\MemcacheManager::getKeyOpenChats($userId));
    if (false === $chats) {
      self::getOpen(true, $userId);
      $chats = $mc->load(\Huhu\Library\MemcacheManager::getKeyOpenChats($userId));
    }

    $senders = Array();


    if (is_array($chats)) {
      foreach ($chats as $chat) {
        $lastReadMessageId = $mc->load(\Huhu\Library\MemcacheManager::getKeyChatLastRead($userId, $chat['id']));


        if (!empty($lastReadMessageId)) {
          $unreadMessages = \Huhu\Library\Chat::getMessages($chat['id'], $lastReadMessageId);

          foreach ($unreadMessages as $m) {
            if (!in_array($m['user_name'], $senders)) {
              $senders[] = $m['user_name'];
            }
          }
        }
      }
    }

    return implode(', ', $senders);
  }


}
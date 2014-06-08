<?php
/**
 * Contains the ChatController class
 */

/**
 * Class ChatController
 *
 * All actions which have directly to do with a chat(room)
 * (open/close chat, join/leave chat, push message...)
 */
class ChatController extends \Huhu\Library\Controller\Action
{

  /**
   * @var array Contains the valiated user input
   */
  private $_validatedInput;


  /**
   * Inits the controller
   * @throws Huhu\Library\Auth\Exception
   */
  public function init()
  {
    /* Initialize action controller here */
    $this->_helper->viewRenderer->setRender('json');

    \Huhu\Library\Auth::auth();

    $this->_validateInput();

    parent::init();
  }



  /**
   * Opens a new chat with a userid (or more than 1)
   */
  public function openAction()
  {
    $currentUser = Zend_Registry::get('loggedinuser');
    $db = Zend_Registry::get('Zend_Db');

    if (!is_array(
      $this->_validatedInput['userids']
    )
    ) {
      $this->_validatedInput['userids'] = Array($this->_validatedInput['userids']);
    }

    // first check if we already have an open chat with the given user(s)
    $existingChatId = null;
    $usersWithMe = $this->_validatedInput['userids'];
    $usersWithMe[] = $currentUser['id'];
    sort($usersWithMe);
    $stmt = $db->prepare(
      "SELECT DISTINCT fk_chatID,
                    (SELECT GROUP_CONCAT(cu2.fk_userID) FROM chats_user AS cu2 WHERE cu2.fk_chatID = cu.fk_chatID GROUP BY cu2.fk_chatID ORDER BY cu2.fk_userID ASC) AS chat_users
                      FROM chats_user AS cu
                    WHERE cu.fk_userID IN (?)"
    );
    if ($stmt->execute(Array(implode(',', $usersWithMe)))) {
      $rows = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);
      foreach ($rows as $row) {
        if ($row['chat_users'] == implode(',', $usersWithMe)) {
          $existingChatId = $row['fk_chatID'];
          break;
        }
      }
    }

    if ($existingChatId) {
      $this->_validatedInput['chatid'] = $existingChatId;
      return $this->reopenAction();
    }


    $chatId = \Huhu\Library\Chat::open($this->_validatedInput['userids']);
    if ($chatId) {
      $users = Array();
      $name = $this->translate->_('Chat with') . ' ';

      // usernames into chat title
      foreach ($this->_validatedInput['userids'] as $userid) {
        if ($userid != $currentUser['id']) {
          $name .= \Huhu\Library\User::getUserName($userid) . " ";
        }
        $users[$userid] = \Huhu\Library\User::getUserName($userid);
      }

      // userpictures into chat title
      $title = $name;
      foreach ($this->_validatedInput['userids'] as $userid) {
        if ($userid != $currentUser['id']) {
          $title .= \Huhu\Library\User::getUserPicture($userid, false, true) . "&nbsp;";
        }
      }

      // push this info to the lucky chatmembers

      $m=$this->translate->_($currentUser['user'] . " has started a chat with you");
      if (count($this->_validatedInput['userids']) > 1) {
        $m=sprintf($this->translate->_($currentUser['user'] . " has started a chat with you and %d others", count($this->_validatedInput['userids']) - 1));
      }

      \Huhu\Library\Push::push(
        $this->_validatedInput['userids'],
        \Huhu\Library\Pusher::assembleOpenChat($m)
      );


      $this->view->dataJson = Array(
        'success' => true,
        'chatid' => $chatId,
        'reopen' => false,
        'name' => $name,
        'title' => $title,
        'users' => $users,
        'messages' => Array(),
      );
    } else {
      $this->view->dataJson = Array(
        'success' => false,
        'message' => $this->translate->_('Chat with this user can\'t be opened, probably he didnt\'t accept your contact yet.'),
      );
    }

    return;
  }


  /**
   * Saves the currently opened chat to memcache
   */
  public function setActiveChatAction()
  {
    if (\Huhu\Library\Chat::setActiveChat($this->_validatedInput['id'])) {
      $this->view->dataJson = Array(
        'success' => true,
      );
    }
  }


  /**
   * Reopens an existing chat (get recent messages)
   */
  public function reopenAction()
  {
    $chat = \Huhu\Library\Chat::reopen($this->_validatedInput['chatid']);

    if ($chat) {
      $this->view->dataJson = Array(
        'success' => true,
        'chatid' => $this->_validatedInput['chatid'],
        'reopen' => true,
        'name' => $chat['name'],
        'title' => $chat['title'],
        'users' => $chat['users'],
        'messages' => $chat['messages'],
      );
    } else {
      $this->view->dataJson = Array(
        'success' => false,
        'message' => $this->translate->_('This chat doesn\'t exist anymore.'),
      );
    }
  }


  /**
   * Returns the title (concatenated string of in-chat users) of the chatid
   * @throws Zend_Exception
   */
  public function getTitleAction()
  {
    $chatid = $this->_validatedInput['chatid'];

    if (empty($chatid)) {
      // use active chat
      $mc = Zend_Registry::get('Zend_Cache');
      $currentUser = Zend_Registry::get('loggedinuser');
      $chatid = $mc->load(\Huhu\Library\MemcacheManager::getKeyActiveChat($currentUser['id']));
    }

    if ($chatid) {
      $chat = \Huhu\Library\Chat::reopen($chatid);
    }

    $this->view->dataJson = Array(
      'success' => true,
      'chatid' => $chatid,
      'title' => (isset($chat['title']) ? $chat['title'] : null),
    );

  }


  /**
   * Closes a chat
   */
  public function closeAction()
  {
    $currentUser = Zend_Registry::get('loggedinuser');

    $completelyClosed = false;
    if (\Huhu\Library\Chat::close($this->_validatedInput['chatid'], $completelyClosed)) {

      $usersInChat = \Huhu\Library\Chat::getUsersInChat($this->_validatedInput['chatid']);
      foreach ($usersInChat as $k => $v) {
        if ($v == $currentUser['id']) {
          unset($usersInChat[$k]);
        }
      }

      // push openchats to affected users, to trigger reload of chatlists/titles
      \Huhu\Library\Push::push(
        $usersInChat,
        \Huhu\Library\Pusher::assembleOpenChat(null)
      );


      if ($completelyClosed) {
        // if the chat was completely closed, push that to trigger window close on the only left users
        \Huhu\Library\Push::push(
          $usersInChat,
          \Huhu\Library\Pusher::assembleClosedChat($this->_validatedInput['chatid'])
        );
      }

      $this->view->dataJson = Array(
        'success' => true,
        'chatid' => $this->_validatedInput['chatid'],
      );
    }
  }


  /**
   * Adds a user to an existing chat
   */
  public function addUserAction()
  {
    $currentUser = Zend_Registry::get('loggedinuser');

    $users = \Huhu\Library\Chat::addUser($this->_validatedInput['chatid'], $this->_validatedInput['userids']);
    if ($users) {
      $usersPush = Array();
      $usersNew = Array();
      foreach ($users as $k => $v) {
        if ($v != $currentUser['id']) {
          $usersPush[] = $k;
        }

        if (in_array($k, $this->_validatedInput['userids'])) {
          $usersNew[$k] = $v;
        }
      }

      // push openchats to affected users, to trigger reload of chatlists/titles
      \Huhu\Library\Push::push(
        $usersPush,
        \Huhu\Library\Pusher::assembleOpenChat(
          $this->translate->_("%s joined the chat", implode(', ', $usersNew))
        )
      );


      $this->view->dataJson = Array(
        'success' => true,
        'message' => $this->translate->_('User added to chat'),
        'chatid' => $this->_validatedInput['chatid'],
        'users' => $users,
      );
    }
  }


  /**
   * Gets all unread messages for this user
   */
  public function getNewMessagesAction()
  {
    $mc = Zend_Registry::get('Zend_Cache');
    $currentUser = Zend_Registry::get('loggedinuser');

    $chatId = $this->_validatedInput['chatid'];
    $messageId = $this->_validatedInput['message_id'];

    if (!$messageId) {
      $messageId = $mc->load(\Huhu\Library\MemcacheManager::getKeyChatLastRead($currentUser['id'], $chatId));
    } else {
      // we got the message id which was received.. so decrement message id by 1

      $expl = explode('_', $messageId);

      $id = base_convert($expl[1], 36, 10);
      --$id;
      $messageId = $expl[0] . '_' . base_convert($id, 10, 36);
    }

    $messages = \Huhu\Library\Chat::getMessages($chatId, $messageId, false, true);

    $this->view->dataJson = Array(
      'success' => true,
      'messages' => $messages,
      'chatId' => $chatId,
    );

  }


  /**
   * Returns list with open chats
   */
  public function getOpenAction()
  {
    $chats = \Huhu\Library\Chat::getOpen();

    $this->view->dataJson = Array(
      'success' => true,
      'chats' => array_values($chats),
    );
  }


  /**
   * returns all recipients in a chatroom inlcuding their public keys
   */
  public function getChatRecipientsAction()
  {
    $users = \Huhu\Library\Chat::getUsersInChatWithPublicKeys($this->_validatedInput['chatid']);
    $this->view->dataJson = Array(
      'success' => true,
      'recipients' => $users,
    );
  }


  /**
   * Push a message to a chat
   */
  public function pushAction()
  {
    $currentUser = Zend_Registry::get('loggedinuser');

    $messages = json_decode($this->_validatedInput['messages'], true);
    if (!$messages) {
      $this->view->dataJson = Array(
        'success' => false,
        'message' => 'Invalid JSON',
      );
      return;
    }

    $messageId = \Huhu\Library\Chat::generateMessageID($this->_validatedInput['chatid']);

    \Huhu\Library\Chat::addMessage($this->_validatedInput['chatid'], $currentUser['id'], $messages, $messageId);

    $usersInChat = \Huhu\Library\Chat::getUsersInChat($this->_validatedInput['chatid']);
    $usersInChatWithoutMe = Array();
    foreach ($usersInChat as $u) {
      if ($u != $currentUser['id']) {
        $usersInChatWithoutMe[] = $u;
      }
    }

    // push to me
    \Huhu\Library\Push::push(
      $currentUser['id'],
      \Huhu\Library\Pusher::assembleChatMessage(
        null,
        $this->_validatedInput['chatid'],
        $messages[$currentUser['id']],
        $currentUser['user'],
        1,
        $messageId
      )
    );

    // push to other users, maybe update last read timestamp
    foreach ($usersInChatWithoutMe as $uid) {
      \Huhu\Library\Push::push(
        $uid,
        \Huhu\Library\Pusher::assembleChatMessage(
          $this->translate->_('New message from ') . \Huhu\Library\Chat::getUnreadMessageSenders($uid),
          $this->_validatedInput['chatid'],
          (isset($messages[$uid]) ? $messages[$uid] : ''),
          $currentUser['user'],
          0,
          $messageId
        )
      );
    }


    $this->view->dataJson = Array(
      'success' => true,
    );
  }


  /**
   * Validates user input (request variables)
   */
  private function _validateInput()
  {
    $filters = Array('*' => Array('StringTrim', 'StripTags'));
    $validators = Array();
    $input = new Zend_Filter_Input($filters, $validators, $this->_getAllParams());


    $this->_validatedInput = Array(
      'chatid' => (int)$input->chatid,
      'filename' => $input->filename,
      'filesize' => (int)$input->filesize,
      'filetype' => $input->filetype,
      'id' => (int)$input->id,
      'message' => $input->message,
      'message_id' => $input->message_id,
      'messages' => $this->_getParam('messages', null),
      'progress' => (int)$input->progress,
      'uploadid' => $input->uploadid,
      'userids' => $input->userids,
    );
  }


}


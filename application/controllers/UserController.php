<?php
/**
 * Contains the UserController class
 */

/**
 * Class UserController
 *
 * Contains the action for managing the user account
 * (signup, login/logout, change profile, get data...)
 */
class UserController extends \Huhu\Library\Controller\Action
{
  /**
   * @var array Contains the valiated user input
   */
  private $_validatedInput = Array();


  /**
   * Inits the controller
   */
  public function init()
  {
    /* Initialize action controller here */
    $this->_helper->viewRenderer->setRender('json');
    $this->_validateInput();

    parent::init();
  }

  /**
   * If the status (online/away/offline) of our logged in user changes, trigger a push event to all users on his contact list
   * resulting all the users to reload the contact list and receive our user's new status
   * @throws Zend_Exception
   */
  private function _pushOnStatusChange()
  {
    $currentUser = Zend_Registry::get('loggedinuser');

    // push contactlist reload to all friends
    $contactList = \Huhu\Library\Contact::getContactList($currentUser['id'], true);
    $contactListPush = Array();
    foreach ($contactList as $c) {
      $contactListPush[] = $c['id'];
    }
    \Huhu\Library\Push::push(
      $contactListPush,
      \Huhu\Library\Pusher::assembleContactlist()
    );

    // push userlist reload to all who have opened chat with this user
    $chatsOpen = \Huhu\Library\Chat::getOpen(true);
    $chatUsersPush = Array();
    foreach ($chatsOpen as $c) {
      foreach ($c['users'] as $u => $foo) {
        if (!in_array($u, $chatUsersPush)) {
          $chatUsersPush[] = $u;
        }
      }
    }
    \Huhu\Library\Push::push(
      $chatUsersPush,
      \Huhu\Library\Pusher::assembleUserlist()
    );
  }


  /**
   * Adds a user to invisible list
   */
  public function addUserToInvisibleListAction()
  {
    \Huhu\Library\Auth::auth();

    if (\Huhu\Library\User::addUserToInvisibleList($this->_validatedInput['userid'])) {

      \Huhu\Library\Push::push(
        $this->_validatedInput['userid'],
        \Huhu\Library\Pusher::assembleOpenChat(null)
      );

      $this->view->dataJson = Array(
        'success' => true,
      );

      return;
    }

    $this->view->dataJson = Array(
      'false' => true,
      'message' => $this->translate->_('Unknown error')
    );
  }


  /**
   * Removes a user to invisible list
   */
  public function removeUserFromInvisibleListAction()
  {
    \Huhu\Library\Auth::auth();

    if (\Huhu\Library\User::removeUserFromInvisibleList($this->_validatedInput['userid'])) {

      \Huhu\Library\Push::push(
        $this->_validatedInput['userid'],
        \Huhu\Library\Pusher::assembleOpenChat(null)
      );


      $this->view->dataJson = Array(
        'success' => true,
      );

      return;
    }

    $this->view->dataJson = Array(
      'false' => true,
      'message' => $this->translate->_('Unknown error')
    );
  }


  /**
   * Does the login
   */
  public function loginAction()
  {
    if ((empty($this->_validatedInput['username']) || empty($this->_validatedInput['password'])) && empty($this->_validatedInput['hash'])) {
      throw new \Huhu\Library\Exception($this->translate->_('Please provide user AND password'));
    }


    $auth = Zend_Auth::getInstance();
    $authAdapter = new Zend_Auth_Adapter_DbTable(
      Zend_Registry::get('Zend_Db'),
      'users',
      'user',
      'password',
      'PASSWORD(?)'
    );

    $username = $this->_validatedInput['username'];
    $password = $this->_validatedInput['password'];

    if (empty($this->_validatedInput['username']) && empty($this->_validatedInput['password']) && !empty($this->_validatedInput['hash'])) {
      // login attempt via hash...
      $credentials = \Huhu\Library\User::decryptLoginHash($this->_validatedInput['hash']);
      $username = $credentials['user'];
      $password = $credentials['password'];
    }

    $result = null;
    if (!empty($username) && !empty($password)) {
      $authAdapter->setIdentity($username)
        ->setCredential($password);


      $result = $auth->authenticate($authAdapter);
    }

    if ($result && $result->isValid()) {
      $session = new Zend_Session_Namespace('compareUser');
      unset($session->compareUser);

      \Huhu\Library\Auth::auth();
      $currentUser = Zend_Registry::get('loggedinuser');
      $session->compareUser = $currentUser['id'];

      // update last login timestamp
      $db = Zend_Registry::get('Zend_Db');
      $db->query(
        "UPDATE users SET lastLoginTimestamp=" . time(
        ) . ", lastLogoutTimestamp=NULL, app_in_background=0 WHERE id = " . (int)$currentUser['id']
      );

      $mc = Zend_Registry::get('Zend_Cache');
      // set last online status in memcache
      $mc->save(true, \Huhu\Library\MemcacheManager::getKeyUserLastOnlineStatus($currentUser['id']));

      // invalidate memcache data for this action
      \Huhu\Library\MemcacheManager::invalidateOnUserStatusUpdate($currentUser['id']);


      // build a login hash to store on the phone or in cookie
      $hash = null;
      if ($this->_validatedInput['keep_loggedin'] == 1) {
        $hash = \Huhu\Library\User::encryptLoginHash($this->_validatedInput['username'], $this->_validatedInput['password']);
      }

      $this->_pushOnStatusChange();

      $this->view->dataJson = Array(
        'success' => true,
        'needsLogin' => false,
        'loggedin' => true,
        'message' => $this->translate->_('Login successful.'),
        'hash' => $hash,
        'needPublicKey' => empty($currentUser['public_key']),
        'userid' => $currentUser['id'],
      );

      // if we have a public key, send testvalue that client should decode to verifiy the private key is correct
      if (!empty($currentUser['public_key'])) {
        $this->view->dataJson['privateKeyTestValue'] = \Huhu\Library\User::generatePrivateKeyTestValue($currentUser['public_key']);
      }

    } else {
      if (!$result) {
        $this->view->dataJson = Array(
          'success' => true,
          'needsLogin' => false,
          'loggedin' => false,
          'message' => $this->translate->_('Please login'),
        );
      } else {
        $message = implode("\n<br />", $result->getMessages());
        switch ($result->getCode()) {
          case Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND:
            /** do stuff for nonexistent identity **/
            $message = $this->translate->_("User does not exist or the password is incorrect.");
            break;

          case Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID:
            /** do stuff for invalid credential **/
            $message =  $this->translate->_("User does not exist or the password is incorrect.");
            break;
        }


        $this->view->dataJson = Array(
          'success' => true,
          'needsLogin' => false,
          'loggedin' => false,
          'message' => $message,
        );
      }
    }
  }


  /**
   * Send own public key to server
   */
  public function sendPublicKeyAction()
  {
    \Huhu\Library\Auth::auth();

    $db = Zend_Registry::get('Zend_Db');
    $currentUser = Zend_Registry::get('loggedinuser');

    $stmt = $db->prepare("UPDATE users SET public_key=COMPRESS(?) WHERE id=?");

    if ($stmt->execute(Array($this->_validatedInput['key'], $currentUser['id']))) {
      \Huhu\Library\MemcacheManager::invalidateOnUserProfileChange($currentUser['id']);

      // reauth
      \Huhu\Library\Auth::auth();

      $contactList = \Huhu\Library\Contact::getContactList($currentUser['id'], true);
      $users = Array();
      foreach ($contactList as $cl) {
        $users[] = $cl['userid'];
      }

      \Huhu\Library\Push::push($users, \Huhu\Library\Pusher::assemblePublicKeyChanged());


      $this->view->dataJson = Array(
        'success' => true,
        'privateKeyTestValue' => \Huhu\Library\User::generatePrivateKeyTestValue($this->_validatedInput['key']),
      );
    }
  }


  /**
   * Requests a private key test value
   */
  public function requestTestPrivateKeyValueAction()
  {
    \Huhu\Library\Auth::auth();
    $currentUser = Zend_Registry::get('loggedinuser');


    $this->view->dataJson = Array(
      'success' => true,
      'privateKeyTestValue' => (empty($currentUser['public_key']) ? null : \Huhu\Library\User::generatePrivateKeyTestValue(
        $currentUser['public_key']
      )),
      'userid' => $currentUser['id'],
      'needPublicKey' => empty($currentUser['public_key']),
    );
  }


  /**
   * Tests if client corretly decrypted the private key test value
   * @throws Zend_Exception
   * @throws \Huhu\Library\Auth\Exception
   */
  public function testPrivateKeyValueAction()
  {
    \Huhu\Library\Auth::auth();
    $currentUser = Zend_Registry::get('loggedinuser');

    $result = \Huhu\Library\User::testPrivateKeyTestValue($this->_validatedInput['value']);
    $this->view->dataJson = Array(
      'success' => true,
      'correct' => $result,
      'needPublicKey' => empty($currentUser['public_key']),
    );
  }


  /**
   * returns public key of given user id
   */
  public function getPublicKeyAction()
  {
    \Huhu\Library\Auth::auth();

    $currentUser = Zend_Registry::get('loggedinuser');

    $db = Zend_Registry::get('Zend_Db');
    $mc = Zend_Registry::get('Zend_Cache');

    // check if this user on my friendlist
    $contacts = \Huhu\Library\Contact::getContactList($currentUser['id'], true);
    $found = false;
    foreach ($contacts as $c) {
      if ($c['userid'] == $this->_validatedInput['userid']) {
        $found = true;
        break;
      }
    }

    if (!$found) {
      $this->view->dataJson = Array(
        'success' => false,
        'message' => $this->translate->_('User not on your contactlist or not accepted yet.'),
      );
      return;
    }

    // ok we are authorized to get this users pubkey
    $pubkey = $mc->load(\Huhu\Library\MemcacheManager::getKeyUserPublicKey($this->_validatedInput['userid']));
    if (false === $pubkey) {
      $res = $db->query(
        "SELECT UNCOMPRESS(public_key) AS public_key FROM users WHERE id=" . (int)$this->_validatedInput['userid']
      );
      if ($res) {
        $row = $res->fetch(Zend_Db::FETCH_ASSOC);
        if (isset($row['public_key'])) {
          $pubkey = $row['public_key'];
          $mc->save($pubkey, \Huhu\Library\MemcacheManager::getKeyUserPublicKey($this->_validatedInput['userid']));
        }
      }
    }

    if (empty($pubkey)) {
      $this->view->dataJson = Array(
        'success' => false,
        'message' => $this->translate->_('No public key available'),
      );
      return;
    }

    $this->view->dataJson = Array(
      'success' => false,
      'pubey' => $pubkey,
    );
  }


  /**
    * logout current user
    */
  public function logoutAction()
  {
    \Huhu\Library\Auth::auth();

    $auth = Zend_Auth::getInstance();
    $auth->clearIdentity();

    // update last logout timestamp
    $currentUser = Zend_Registry::get('loggedinuser');
    $db = Zend_Registry::get('Zend_Db');
    $mc = Zend_Registry::get('Zend_Cache');

    $db->query("UPDATE users SET lastLogoutTimestamp=" . time() . " WHERE id = " . (int)$currentUser['id']);
    $db->query("DELETE FROM user_push_auth WHERE fk_userID=" . (int)$currentUser['id']);


    // set last online status in memcache
    $mc->save(false, \Huhu\Library\MemcacheManager::getKeyUserLastOnlineStatus($currentUser['id']));


    // invalidate memcache data for this action
    \Huhu\Library\MemcacheManager::invalidateOnUserStatusUpdate($currentUser['id']);

    $this->_pushOnStatusChange();

    Zend_Registry::set('loggedinuser', null);

    $session = new Zend_Session_Namespace('compareUser');
    unset($session->compareUser);

    Zend_Session::regenerateId();

    $this->view->dataJson = Array(
      'success' => true,
    );

  }


  /**
   * Tests if user is loggedin
   */
  public function checkLoggedinAction()
  {
    try {
      \Huhu\Library\Auth::auth();

      $currentUser = Zend_Registry::get('loggedinuser');

      $this->view->dataJson = Array(
        'success' => true,
        'isLoggedIn' => true,
        'userid' => $currentUser['id'],
      );
    } catch (\Huhu\Library\Auth\Exception $e) {
      $this->view->dataJson = Array(
        'success' => true,
        'isLoggedIn' => false,
      );
    }
  }


  /**
   * Does the signup
   */
  public function signupAction()
  {
    $db = Zend_Registry::get('Zend_Db');
    try {
      // prechecks:
      // password length ok
      if (strlen($this->_validatedInput['password']) < 8) {
        throw new \Huhu\Library\Exception($this->translate->_('Password must be at least 8 characters long'));
      }
      // password match
      if ($this->_validatedInput['password'] != $this->_validatedInput['password2']) {
        throw new \Huhu\Library\Exception($this->translate->_('Passwords don\'t match.'));
      }
      // email given
      if (empty($this->_validatedInput['email']) || !filter_var(
          $this->_validatedInput['email'],
          FILTER_VALIDATE_EMAIL
        )
      ) {
        throw new \Huhu\Library\Exception(
          $this->translate->_('No valid e-mail address given (is needed for password recovery!).')
        );
      }


      // username unique
      $stmt = $db->prepare("SELECT id FROM users WHERE user=?");
      $stmt->execute(Array($this->_validatedInput['username']));
      $stmt->fetchAll(); // to prevent unbuffered queries error
      if ($stmt->rowCount() > 0) {
        // username exists... make some suggestions

        $suggestions = Array(
          $this->_validatedInput['username'] . '1',
          $this->_validatedInput['username'] . '123',
          $this->_validatedInput['username'] . '321',
          $this->_validatedInput['username'] . '345',
        );

        // more suggestions from additionalFields
        if (is_array($this->_validatedInput['additional'])) {
          foreach ($this->_validatedInput['additional'] as $v) {
            if (is_scalar($v) && preg_match('/^[A-Za-z0-9_\-]*$/', $v)) {
              // good for username
              array_unshift($suggestions, $this->_validatedInput['username'] . ucfirst($v));
            }
          }
        }

        // check if suggestions are really free
        // ensure no sql injection is done
        array_walk(
          $suggestions,
          function (&$val, $key) {
            $db = Zend_Registry::get('Zend_Db');
            $val = $db->quote($val);
          }
        );
        $res = $db->query("SELECT user FROM users WHERE user IN (" . implode(',', $suggestions) . ")");
        $suggestionUsers = $res->fetchAll(Zend_Db::FETCH_ASSOC);
        foreach ($suggestionUsers as $row) {
          // remove from array
          unset($suggestions[array_search($row['user'], $suggestions)]);
        }

        $msg = $this->translate->_('Unfortunately this username already exists..<br />How about this user names which are currently available?').'<br /><br /><ul>';
        foreach ($suggestions as $suggestion) {
          $msg .= '<li>' . $suggestion . '</li>';
        }
        $msg .= '</ul>';
        throw new \Huhu\Library\Exception($msg);
      } // end: check username exists

      // we got thru here? then everything ok -> insert user
      $stmt = $db->prepare("INSERT INTO users (user, password, email) VALUES (?, PASSWORD(?), ?)");
      if ($stmt->execute(
        Array(
          $this->_validatedInput['username'],
          $this->_validatedInput['password'],
          $this->_validatedInput['email']
        )
      )
      ) {
        $insertId = $db->lastInsertId();
        $stmt->closeCursor();

        if ($this->_validatedInput['emailpublic']) {
          if (!is_array($this->_validatedInput['additional'])) {
            $this->_validatedInput['additional'] = Array();
          }

          $this->_validatedInput['additional']['email'] = $this->_validatedInput['email'];
        }

        // maybe insert additional fields
        if (is_array($this->_validatedInput['additional'])) {
          $query = '';
          $params = Array();
          foreach ($this->_validatedInput['additional'] as $field => $value) {
            if (is_array($value)) {
              $value = implode(';', $value);
            }

            if (!empty($value)) {

              if ($field == 'birthday') {
                $value = $this->_formatDate($value);
              }


              $query .= "INSERT INTO user_additional (fk_userID, field, value) VALUES (?,?,?);\n";
              $params[] = $insertId;
              $params[] = $field;
              $params[] = $value;
            }
          }
          if (count($params)) {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $stmt->closeCursor();
          }
        }

        // login action to set Zend_Auth identity
        $this->view->dataJson = Array();
        $this->loginAction();
        $this->view->dataJson['message'] = sprintf($this->translate->_('Welcome %s'), $this->_validatedInput['username']);
        $this->view->dataJson['valid'] = 1;
        $this->view->dataJson['userid'] = $insertId;


      }
    } catch (\Huhu\Library\Exception $e) {
      $this->view->dataJson = Array(
        'success' => true,
        'valid' => false,
        'message' => $e->getMessage(),
      );
    }
  }


  /**
   * gets profile data
   */
  public function profileGetAction()
  {
    \Huhu\Library\Auth::auth();
    $currentUser = Zend_Registry::get('loggedinuser');

    $this->view->dataJson = Array(
      'success' => true,
      'user' => \Huhu\Library\User::getUserByUsername($currentUser['user']),
      'userid' => $currentUser['id'],
    );
  }


  /**
   * Updates user profile
   * @throws \Huhu\Library\Exception
   */
  public function profileUpdateAction()
  {
    \Huhu\Library\Auth::auth();

    $passwordChanged = false;
    $allUpdated = true;

    $db = Zend_Registry::get('Zend_Db');
    try {
      // prechecks:
      // password length ok
      if (!empty($this->_validatedInput['passwordold'])) {
        if (strlen($this->_validatedInput['password']) < 8) {
          throw new \Huhu\Library\Exception($this->translate->_('The password must be at least 8 characters long.'));
        }
        // password match
        if ($this->_validatedInput['password'] != $this->_validatedInput['password2']) {
          throw new \Huhu\Library\Exception($this->translate->_('The passwords don\'t match.'));
        }
      }

      // email given
      if (empty($this->_validatedInput['email']) || !filter_var(
          $this->_validatedInput['email'],
          FILTER_VALIDATE_EMAIL
        )
      ) {
        throw new \Huhu\Library\Exception(
          $this->translate->_('No valid e-mail address given (is needed for password recovery!).')
        );
      }


      // we got thru here? then everything ok -> update user
      $userOld = \Huhu\Library\User::getUserByUsername($this->_validatedInput['username']);
      if ($userOld) {
        $updatedFields = Array();
        if ($this->_validatedInput['email'] != $userOld['email']) {
          $updatedFields['email'] = $this->_validatedInput['email'];
        }

        if (!empty($this->_validatedInput['passwordold'])) {
          $testPw = $db->query(
            "SELECT id FROM users WHERE password=PASSWORD(" . $db->quote($this->_validatedInput['passwordold']) . ")"
          );
          $testPwRow = $testPw->fetch(Zend_Db::FETCH_ASSOC);
          if ($testPwRow['id'] != $userOld['id']) {
            throw new \Huhu\Library\Exception($this->translate->_('The old password is not correct.'));
          } else {
            $passwordChanged = true;
            $updatedFields['password'] = $this->_validatedInput['password'];
          }
        }

        if (!is_array($this->_validatedInput['additional'])) {
          $this->_validatedInput['additional'] = Array();
        }
        if ($this->_validatedInput['emailpublic'] == 1) {
          $this->_validatedInput['additional']['email'] = $this->_validatedInput['email'];
        } else {
          $this->_validatedInput['additional']['email'] = '';
        }

        if (count($updatedFields)) {
          $setStr = '';
          $params = Array();
          foreach ($updatedFields as $key => $val) {
            if (!empty($setStr)) {
              $setStr .= ', ';
            }
            if ($key == 'password') {
              $setStr .= $key . '=PASSWORD(?)';
            } else {
              $setStr .= $key . '=?';
            }
            $params[] = $val;
          }

          $stmt = $db->prepare("UPDATE users SET " . $setStr . " WHERE id = " . (int)$userOld['id']);

          $allUpdated = false;
          if ($stmt->execute($params)) {
            $stmt->closeCursor();
            $allUpdated = true;
          }
        }


        // maybe insert/update/delete additional fields
        if ($allUpdated && is_array($this->_validatedInput['additional'])) {
          foreach ($this->_validatedInput['additional'] as $field => $value) {
            if (is_array($value)) {
              $value = implode(';', $value);
            }

            $query = "";
            $params = Array();

            if (!array_key_exists($field, $userOld['additional'])) {
              // new field

              if ($field == 'birthday') {
                $value = $this->_formatDate($value);
              }

              $query = "INSERT INTO user_additional (fk_userID, field, value) VALUES(?,?,?);";
              $params = Array($userOld['id'], $field, $value);
            } else {
              if (empty($value)) {
                // delete field
                $query = "DELETE FROM user_additional WHERE fk_userID=? AND field=?";
                $params = Array($userOld['id'], $field);
              } else {
                if ($value != $userOld['additional'][$field]) {
                  // update field
                  $query = "UPDATE user_additional SET value = ? WHERE fk_userID=? AND field=?";
                  $params = Array($value, $userOld['id'], $field);
                }
              }
            }

            if (!empty($query)) {
              $allUpdated = false;
              $stmt = $db->prepare($query);
              if ($stmt->execute($params)) {
                $stmt->closeCursor();
                $allUpdated = true;
              }
            }
          }
        }


        if ($passwordChanged) {
          $this->logoutAction();
        }

        if ($allUpdated) {
          $this->_pushOnStatusChange();

          $this->view->dataJson = Array(
            'success' => true,
            'message' => $this->translate->_('Successfully saved.'),
          );
        } else {
          $this->view->dataJson = Array(
            'success' => false,
            'message' => $this->translate->_('Save was not completed with errors'),
          );
        }
      } else {
        throw new \Huhu\Library\Exception($this->translate->_('Username invalid'));
      }
    } catch (\Huhu\Library\Exception $e) {
      $this->view->dataJson = Array(
        'success' => true,
        'valid' => false,
        'message' => $e->getMessage(),
      );
    }
  }


  /**
   * Generates a new password, saves it to the database and send an email to the user
   */
  public function resetPasswordAction()
  {
    $username = $this->_validatedInput['username'];

    $user = \Huhu\Library\User::getUserByUsername($username);

    if ($user) {
      // generate random password phrase
      $str = '';
      for ($i = 0; $i < 8; $i++) {
        srand();
        if ($i % 2) {
          $str .= chr(rand(48, 91));
        } else {
          $str .= chr(rand(97, 122));
        }

      }

      // reset password
      if (\Huhu\Library\User::saveUser($user['id'], Array('password' => $str))) {
        // mail new password to user
        mail(
          $user['email'],
          $this->translate->_('Your new password'),
          $this->translate->_("Hello " . $user['name'] . "\n\nyou have forgotten your password. We created a new one for you, here it is:\n\n" . $str . "\n\nPlease login with your new password, now and PLEASE CHANGE IT, it is not so secure.\n\nWith best wishes\nThe Huhu-Team"),
          'FROM: Huhu<noreply@we-hu.hu>'
        );

        $this->view->dataJson = Array(
          'success' => true,
          'message' => $this->translate->_('A new password was created and will be delivered to your email address'),
        );
      }
    } else {
      $this->view->dataJson = Array(
        'success' => false,
        'message' => $this->translate->_('Username not found'),
      );
    }
  }


  /**
   * Saves if the app was sent to background
   */
  public function setAppInBackgroundAction()
  {
    \Huhu\Library\Auth::auth();

    $db = Zend_Registry::get('Zend_Db');
    $mc = Zend_Registry::get('Zend_Cache');
    $currentUser = Zend_Registry::get('loggedinuser');

    \Huhu\Library\MemcacheManager::invalidateOnUserStatusUpdate($currentUser['id']);

    $mc->save($this->_validatedInput['flag'], \Huhu\Library\MemcacheManager::getKeyAppInBackground($currentUser['id']));

    $stmt = $db->prepare("UPDATE users SET app_in_background=? WHERE id=?");
    if ($stmt->execute(Array($this->_validatedInput['flag'], $currentUser['id']))) {

      try {
        $this->_pushOnStatusChange();
      } catch (Exception $e) {
      }

      $this->view->dataJson = Array(
        'success' => true,
      );
      return;
    }

    $this->view->dataJson = Array(
      'success' => false,
    );
  }


  /**
   * Checks if the currently logged in user is in invisible mode
   * @throws \Huhu\Library\Auth\Exception
   */
  public function getInvisibleAction()
  {
    \Huhu\Library\Auth::auth();

    $invisible = \Huhu\Library\User::getInvisible();

    $this->view->dataJson = Array(
      'success' => true,
      'invisible' => (int)$invisible,
    );

  }


  /**
   * Sets if the currently logged in user is in invisible mode
   * @throws \Huhu\Library\Auth\Exception
   */
  public function setInvisibleAction()
  {
    \Huhu\Library\Auth::auth();

    if (\Huhu\Library\User::setInvisible($this->_validatedInput['flag'])) {

      $this->_pushOnStatusChange();

      $this->view->dataJson = Array(
        'success' => true,
      );
      return;
    }

    $this->view->dataJson = Array(
      'success' => false,
      'message' => $this->translate->_('Unknown error.')
    );
  }


  /**
   * Generates a websocket token for authentificaton on websocket push server
   * and stores it to the pushauth db
   */
  public function pushauthGenerateWebsocketTokenAction()
  {
    \Huhu\Library\Auth::auth();

    $token = \Huhu\Library\Push::pushauthGenerateWebsocketToken();

    if ($token) {
      $this->view->dataJson = Array(
        'success' => true,
        'token' => $token,
      );
    } else {
      throw new \Huhu\Library\Exception('No Token generated.');
    }
  }


  /**
   * accepts the googleCloudMessage regID, generated by app/device
   * and stores it to the pushauth db
   */
  public function pushauthSetGcmRegidAction()
  {
    \Huhu\Library\Auth::auth();

    if (\Huhu\Library\Push::pushauthSetGcmRegid($this->_validatedInput['regid'])) {
      $this->view->dataJson = Array(
        'success' => true,
      );
    } else {
      throw new \Huhu\Library\Exception('RegID not saved.');
    }
  }


  /**
   * accepts the apn (Apple push notification service) token generated by app/device
   * and stores it to the pushauth db
   * @throws \Huhu\Library\Auth\Exception
   * @throws \Huhu\Library\Exception
   */
  public function pushauthSetApnTokenAction()
  {
    \Huhu\Library\Auth::auth();

    if (\Huhu\Library\Push::pushauthSetApnToken($this->_validatedInput['token'])) {
      $this->view->dataJson = Array(
        'success' => true,
      );
    } else {
      throw new \Huhu\Library\Exception('Token not saved.');
    }
  }


  /**
   * Staying alive, staying alive, ha ha ha...
   * (is requested frequently by the app to confirm user is still online)
   */
  public function heartbeatAction()
  {
    \Huhu\Library\Auth::auth();
    $currentUser = Zend_Registry::get('loggedinuser');
    $mc = Zend_Registry::get('Zend_Cache');

    $mc->save(time(), \Huhu\Library\MemcacheManager::getKeyUserHeartbeat($currentUser['id']));

    $this->view->dataJson = Array(
      'success' => true,
    );
  }


  /**
   * Uploads a new profile picture (via fileupload!)
   */
  public function uploadProfilePicFileAction()
  {
    header('Content-Type: text/html');

    \Huhu\Library\Auth::auth();
    $currentUser = Zend_Registry::get('loggedinuser');


    $destFile = $_SERVER['DOCUMENT_ROOT'] . '/../tmp/' . uniqid($_FILES['file']['name']);
    if (move_uploaded_file($_FILES['file']['tmp_name'], $destFile)) {
      // upload OK

      $imgObj = null;

      switch (mime_content_type($destFile)) {
        case 'image/jpeg':
        case 'image/jpg':
          $imgObj = imagecreatefromjpeg($destFile);
          break;

        case 'image/gif':
          $imgObj = imagecreatefromgif($destFile);
          break;

        case 'image/png':
          $imgObj = imagecreatefrompng($destFile);
          break;

        default:
          $this->view->dataJson = Array(
            'success' => false,
            'message' => $this->translate->_('This is not a valid image file. We support all images in the format JPG, PNG or GIF.'),
          );
          return;
          break;
      }

      if (!$imgObj) {
        $this->view->dataJson = Array(
          'success' => false,
          'message' => $this->translate->_('The image is corrupted or in a wrong format. We support all images in the format JPG, PNG or GIF.'),
        );
        return;
      }

      // we have imgObj and do not need the file any longer
      unlink($destFile);

      // now we can work with the image
      if (\Huhu\Library\User::setProfilePicture($currentUser['id'], $imgObj)) {

        $this->_pushOnStatusChange();

        $this->view->dataJson = Array(
          'success' => true,
          'src' => \Huhu\Library\User::getUserPicture($currentUser['id'], true, false, true)
        );
        return;
      }
    }

    @unlink($destFile);
    $this->view->dataJson = Array(
      'success' => false,
      'message' => $this->translate->_('Upload failed'),
    );
  }


  /**
   * Sencha Touch sends date as it wants today, so detect current format and reformat it to a unique date format
   * @param string $date
   * @return string $date
   */
  private function _formatDate($date)
  {
    $expl = explode('-', $date);
    if (count($expl) < 3) {
      $expl = explode('.', $date);
    }
    if (count($expl) > 2) {
      if (strlen($expl[0]) == 4) {
        // yyyy mm dd
        return $expl[0] . '-' . $expl[1] . '-' . $expl[2];
      } elseif (strlen($expl[2]) == 4) {
        // dd mm yyyy
        return $expl[2] . '-' . $expl[1] . '-' . $expl[0];
      }
    }

    return $date; // we can't do anything...
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
      'additional' => (isset($_REQUEST['additional']) ? $_REQUEST['additional'] : null),
      // will be filtered few lines below
      'email' => $input->email,
      'emailpublic' => (int)$input->emailpublic,
      'flag' => (int)$input->flag,
      'hash' => $input->hash,
      'keep_loggedin' => (int)$input->keep_loggedin,
      'key' => $input->key,
      'password' => $input->password,
      'password2' => $input->password2,
      'passwordold' => $input->passwordold,
      'regid' => $input->regid,
      'token' => $input->token,
      'userid' => (int)$input->userid,
      'username' => $input->username,
      'value' => $input->value,
    );

    if (is_array($this->_validatedInput['additional'])) {
      $filterChain = new Zend_Filter();
      $filterChain->addFilter(new Zend_Filter_StringTrim())
        ->addFilter(new Zend_Filter_StripTags());

      array_walk_recursive(
        $this->_validatedInput['additional'],
        function (&$val, $index, $filterChain) {
          $val = $filterChain->filter($val);
        },
        $filterChain
      );
    } else {
      $this->_validatedInput['additional'] = null;
    }
  }
}


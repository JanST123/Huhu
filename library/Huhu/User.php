<?php
/**
 * Contains the User class
 */

namespace Huhu\Library;

/**
 * Class User
 * Helper methods for user management
 */
class User
{
  /**
   * @var array contains users which changed the status
   */
  private static $_usersChangedStatus=Array();
  /**
   * @var string the login hash salt
   */
  private static $_loginHashSalt='babba83210acdf09fa88801ebb1ab288a4188093389adda8728109aca56feddd';

  /**
   * @var int status user is offline (or invisible)
   */
  public static $USER_STATUS_OFFLINE=0;
  /**
   * @var int status user is online and present (heartbeat received in the past few minutes)
   */
	public static $USER_STATUS_ONLINE=1;
  /**
   * @var int status user is away (app went to background)
   */
	public static $USER_STATUS_AWAY=2;


  /**
   * Generates a testvalue and encrypts it with our public key, to test if client can decode
   * @param string $pubKey
   * @return string crypted value
   */
  public static function generatePrivateKeyTestValue($pubKey) {
    $testValue=uniqid();
    $session=new \Zend_Session_Namespace('UserPrivateKeyTest');
    $session->testValue=$testValue;

    // encrypt with the pubkey
    $crypted=null;
    openssl_public_encrypt($testValue, $crypted, $pubKey);

    return base64_encode($crypted);
  }


  /**
   * tests if client has correctly decoded our generated testvalue
   * @param $value (base64 encoded)
   * @return boolean success
   */
  public static function testPrivateKeyTestValue($value) {
    $session=new \Zend_Session_Namespace('UserPrivateKeyTest');

    if (isset($session->testValue) && $session->testValue == $value) {
      return TRUE;
    }

    return FALSE;
  }


  /**
   * Returns user data by user name
   * @param int $userId
   * @return array $user
   */
  public static function getUserByID($userId) {
    $db=\Zend_Registry::get('Zend_Db');

    $stmt=$db->prepare("SELECT u.id AS user_id, u.*, ua.* FROM users AS u
							LEFT JOIN user_additional AS ua ON ua.fk_userID = u.id
							WHERE u.id = ?");

    $user=Array();

    if ($stmt->execute(Array($userId))) {
      $rows=$stmt->fetchAll(\Zend_Db::FETCH_ASSOC);
      if ($stmt->rowCount() < 1) {
        return FALSE;
      }

      foreach ($rows as $row) {
        if (!isset($user['additional'])) $user['additional']=Array();
        $user['id'] = $row['user_id'];
        $user['name'] = $row['user'];
        $user['email'] = $row['email'];
        $user['lastLogoutTimestamp'] = $row['lastLogoutTimestamp'];

        if (!empty($row['field']) && !empty($row['value'])) {
          $user['additional'][$row['field']]=$row['value'];
        }

        $user['image']=\Huhu\Library\User::getUserPicture($row['user_id'], TRUE, FALSE, TRUE);
      }
    }

    return $user;
  }


	/** 
	 * Returns user data by user name
	 * @param string $username
   * @return array $user
	 */
	public static function getUserByUsername($username) {
		$db=\Zend_Registry::get('Zend_Db');

		$stmt=$db->prepare("SELECT u.id AS user_id, u.*, ua.* FROM users AS u
							LEFT JOIN user_additional AS ua ON ua.fk_userID = u.id
							WHERE u.user = ?");
		
		$user=Array();
		
		if ($stmt->execute(Array($username))) {
			$rows=$stmt->fetchAll(\Zend_Db::FETCH_ASSOC);
			if ($stmt->rowCount() < 1) {
				return FALSE;
			}
				
			foreach ($rows as $row) {
				if (!isset($user['additional'])) $user['additional']=Array();
				$user['id'] = $row['user_id'];
				$user['name'] = $row['user'];
				$user['email'] = $row['email'];
        $user['lastLogoutTimestamp'] = $row['lastLogoutTimestamp'];

				if (!empty($row['field']) && !empty($row['value'])) {
					$user['additional'][$row['field']]=$row['value'];
				}
				
				$user['image']=\Huhu\Library\User::getUserPicture($row['user_id'], TRUE, FALSE, TRUE);
			}
		}
		
		return $user;
	}
	
	
	/**
	 * Updates the given fields for the given user
	 * @param int $id
	 * @param array $fields
   * @return bool $success
	 */
	public static function saveUser($id, $fields) {
		$db=\Zend_Registry::get('Zend_Db');

		
		$query="UPDATE users SET ";
		$params=Array();
		$i=0;
		foreach ($fields as $key => $val) {
			if ($i) $query.=", ";
			if ($key=='password') {
				$query.='`'.$key.'`=PASSWORD(:'.$key.')';
			} else {
				$query.='`'.$key.'`=:'.$key;
			}
			$params[':'.$key]=$val;
			
			++$i;
		}
		$query.=" WHERE id=".(int)$id;
		
		$stmt=$db->prepare($query);
		if ($stmt->execute($params)) {
			$stmt->closeCursor();
			
			\Huhu\Library\MemcacheManager::invalidateOnUserProfileChange($id);
			
			return TRUE;
		}
		
		return FALSE;
	}


  /**
   * Determines if user is online due to the last ping timestamp
   * @param int $userId
   * @param bool| $returnAsString Return 'online', 'offline' as text
   * @throws \Zend_Exception
   * @return int|string
   */
	public static function getOnlineStatus($userId, $returnAsString=FALSE) {
		$mc=\Zend_Registry::get('Zend_Cache');
    $translate=\Zend_Registry::get('Zend_Translate');



    /*
    - Detect online status:
        -> ONLINE: Heartbeat timestamp was updated within the last 35 seconds, app_in_background==0, and no Logout Timestamp set
        -> AWAY: Valid push method present OR app_in_background==1 AND no Logout Timestamp
        -> OFFLINE: else
    */
		

		
		// first check if user is globally invisible or if user is invisible to me...
		$usersInvisibleToMe=self::getUsersInvisibleToMe();

		if (self::getInvisible($userId) || in_array($userId, $usersInvisibleToMe)) {
			// offline
			$status=self::$USER_STATUS_OFFLINE;
		} else {
      // check the heartbeat
      $heartbeat=$mc->load(\Huhu\Library\MemcacheManager::getKeyUserHeartbeat($userId));
			$appInBackground=$mc->load(\Huhu\Library\MemcacheManager::getKeyAppInBackground($userId));
      $validPushMethods=\Huhu\Library\Push::getUserMethods($userId);
      $userData=\Huhu\Library\User::getUserByID($userId);



      if ((time() - $heartbeat) < 35 && $appInBackground==0 && empty($userData['lastLogoutTimestamp'])) {
        $status=self::$USER_STATUS_ONLINE;
      } else if ((is_array($validPushMethods) && count($validPushMethods)) || ($appInBackground==1 && empty($userData['lastLogoutTimestamp']))) {
        $status=self::$USER_STATUS_AWAY;
      } else {
        $status=self::$USER_STATUS_OFFLINE;
      }

			

			$lastOnlineStatus=$mc->load(\Huhu\Library\MemcacheManager::getKeyUserLastOnlineStatus($userId));

			if ($status != $lastOnlineStatus) {
				// status has changed, invalidate all memcache keys
				\Huhu\Library\MemcacheManager::invalidateOnUserStatusUpdate($userId);
				$mc->save($status, \Huhu\Library\MemcacheManager::getKeyUserLastOnlineStatus($userId));
			}
		}		


		if ($returnAsString) {
			switch ($status) {
				case self::$USER_STATUS_OFFLINE:
					return $translate->_('Offline');
					break;
					
				case self::$USER_STATUS_ONLINE:
					return $translate->_('Online');
					break;
					
				case self::$USER_STATUS_AWAY:
					return $translate->_('Away');
					break;
			}
		}
		
		return $status;
	}
	
	
	/**
	 * Returns all users who's online status has changed since last reset
	 */
	public static function getUsersWithChangedStatus() {
		return self::$_usersChangedStatus;
	}



	/**
	 * Resets the list of users with recently changed status
	 */
	public static function resetUsersWithChangedStatus() {
		self::$_usersChangedStatus=Array();
	}
	
	
	/**
	 * Returns user name to id
	 * @param int $userid
	 */
	public static function getUserName($userid) {
		$db=\Zend_Registry::get('Zend_Db');
		$mc=\Zend_Registry::get('Zend_Cache');
		
		$name=$mc->load(\Huhu\Library\MemcacheManager::getKeyUserName($userid));
		
		if ($name===FALSE) {
			$res=$db->query("SELECT user FROM users WHERE id = ".(int)$userid);
			if ($res && $res->rowCount()) {
				$row=$res->fetch(\Zend_Db::FETCH_ASSOC);
				$name=$row['user'];
				$mc->save($name, \Huhu\Library\MemcacheManager::getKeyUserName($userid), array(), 300);
			}
		}
		
		return $name;
	}


  /**
   * Returns if a user is global invisible
   * @param int $userId
   * @return int
   * @throws \Zend_Exception
   */
  public static function getInvisible($userId=null) {
		$db=\Zend_Registry::get('Zend_Db');
		$mc=\Zend_Registry::get('Zend_Cache');
		$currentUser=\Zend_Registry::get('loggedinuser');
		
		if (null===$userId) $userId=$currentUser['id'];
		
		$invisible=$mc->load(\Huhu\Library\MemcacheManager::getKeyUserInvisible($userId));
		if ($invisible===FALSE) {
			$res=$db->query("SELECT invisible FROM users WHERE id=".(int)$userId);
			$row=$res->fetch(\Zend_Db::FETCH_ASSOC);
			$invisible=(int)$row['invisible'];
			
			$mc->save($invisible, \Huhu\Library\MemcacheManager::getKeyUserInvisible($userId));
		}
		
		return $invisible;
	}


  /**
   * Sets a user to global invisible
   * @param bool $invisible
   * @return bool
   * @throws \Zend_Exception
   */
  public static function setInvisible($invisible) {
		$db=\Zend_Registry::get('Zend_Db');
		$mc=\Zend_Registry::get('Zend_Cache');
		$currentUser=\Zend_Registry::get('loggedinuser');
		
		$stmt=$db->prepare("UPDATE users SET invisible=? WHERE id=?");
		if ($stmt->execute(Array((int)$invisible, $currentUser['id']))) {
			\Huhu\Library\MemcacheManager::invalidateOnUserStatusUpdate($currentUser['id']);
			$mc->save((int)$invisible, \Huhu\Library\MemcacheManager::getKeyUserInvisible($currentUser['id']));
			return TRUE;
		}
		return FALSE;
	}


  /**
   * Sets a new profile picture for given user, receives base64 encoded image JPEG Data
   * @param int $userid
   * @param resource $img
   * @throws \Huhu\Library\Exception
   * @internal param $ressource $img
   * @return bool $success
   */
	public static function setProfilePicture($userid, $img) {
		$db=\Zend_Registry::get('Zend_Db');
		
		// first check if valid  image
		if ($img) {
			// valid - create 2 images -> a small and a "big" one
			$small=20;
			$big=64;
			
			// to detect imagesize in php5.3 we need a file.. -.-
			$tmpfile=tempnam(APPLICATION_PATH.'/../../tmp', 'profilepictmp');
			imagejpeg($img, $tmpfile);
			$imgSize=getimagesize($tmpfile);
			unlink($tmpfile);
			
			$newHeightSmall=null;
			$newWidthSmall=null;
			$newHeightBig=null;
			$newWidthBig=null;
			
			if ($imgSize[0] > $imgSize[1]) {
				// landscape
				$newWidthBig=$big;
				$newWidthSmall=$small;
				
				$newHeightBig=$imgSize[1] * ($big / $imgSize[0]);
				$newHeightSmall=$imgSize[1] * ($small / $imgSize[0]);
			} else {
				// portrait
				$newHeightBig=$big;
				$newHeightSmall=$small;
				
				$newWidthBig=$imgSize[0] * ($big / $imgSize[1]);
				$newWidthSmall=$imgSize[0] * ($small / $imgSize[1]);
			}
			$imgBig=imagecreatetruecolor($newWidthBig, $newHeightBig);
			$imgSmall=imagecreatetruecolor($newWidthSmall, $newHeightSmall);
			
			if (imagecopyresized($imgBig, $img, 0, 0, 0, 0, $newWidthBig, $newHeightBig, $imgSize[0], $imgSize[1])
			 && imagecopyresized($imgSmall, $img, 0, 0, 0, 0, $newWidthSmall, $newHeightSmall, $imgSize[0], $imgSize[1])) {
				
				ob_start();
				imagejpeg($imgBig, null, 90);
				$imgBigData=ob_get_clean();
				
				ob_start();
				imagejpeg($imgSmall, null, 90);
				$imgSmallData=ob_get_clean();
				
				
				// save this to database...
				$stmt=$db->prepare("UPDATE users SET photo=?, photo_big=?, photo_width=?, photo_height=?, photo_big_width=?, photo_big_height=? WHERE id=?");
				$params=Array(
							$imgSmallData, 
							$imgBigData,
							$newWidthSmall,
							$newHeightSmall,
							$newWidthBig,
							$newHeightBig, 	
							$userid,
						);
				
				if ($stmt->execute($params)) {
					// invalidate some memcache entries..
					\Huhu\Library\MemcacheManager::invalidateOnUserProfileChange($userid);
					return TRUE;
				}
			}
		} else {
			throw new \Huhu\Library\Exception('Invalid Image received');
		}

		return FALSE;
		
	}
	
	
	/**
	 * Returns user picture to id
	 * @param int $userid
   * @param bool $dataOnly
   * @param bool $includeUserStatus
   * @param bool $big
   * @return string image data or html
	 */
	public static function getUserPicture($userid, $dataOnly=FALSE, $includeUserStatus=FALSE, $big=FALSE) {
		$db=\Zend_Registry::get('Zend_Db');
		$mc=\Zend_Registry::get('Zend_Cache');
		$currentUser=\Zend_Registry::get('loggedinuser');
		
		$mcKey=\Huhu\Library\MemcacheManager::getKeyUserPicture($userid, $big);
		if ($includeUserStatus) {
			$mcKey=\Huhu\Library\MemcacheManager::getKeyUserPictureWithStatus($userid, $currentUser['id'], $big);
		}
		$pic=$mc->load($mcKey);

		if ($pic===FALSE) {
			$imgSize=Array();
			
			$photofield='photo';
			if ($big) $photofield='photo_big';
			 
			$res=$db->query("SELECT ".$photofield.", ".$photofield."_width, ".$photofield."_height FROM users WHERE id = ".(int)$userid);
			if ($res && $res->rowCount()) {
				$row=$res->fetch(\Zend_Db::FETCH_ASSOC);
				$pic=$row[$photofield];
				$imgSize[0]=$row[$photofield.'_width'];
				$imgSize[1]=$row[$photofield.'_height'];
			}
			
			if (empty($pic)) {
				$pic=file_get_contents(APPLICATION_PATH.'/resources/defaultUserPic.jpg');
				if ($big) {
					$imgSize=Array(64,64);
				} else {
					$imgSize=Array(20,20);
				}
			}

			if ($includeUserStatus) {
				// place the status icon into the lower right edge of the user pic
				$imgObj=imagecreatefromstring($pic);
					
	
				if ($imgObj) {
					// get status icon
					
					// check if accepted on contact list
					$accepted=true;
					$res=$db->query("SELECT accepted FROM contactlist WHERE (fk_ownerUserID=".(int)$currentUser['id']." OR fk_ownerUserID=".(int)$userid.") AND (fk_contactUserID=".(int)$currentUser['id']." OR fk_contactUserID=".(int)$userid.")");
					if ($res) {
						$row=$res->fetch(\Zend_Db::FETCH_ASSOC);
						$accepted=$row['accepted'];
					}
					
					if ($accepted) {
						$status=\Huhu\Library\User::getOnlineStatus($userid);
						$statusImgSrc=null;
						switch ($status) {
							case \Huhu\Library\User::$USER_STATUS_ONLINE:
								$statusImgSrc=APPLICATION_PATH.'/resources/online.png';
								break;
							case \Huhu\Library\User::$USER_STATUS_OFFLINE:
								$statusImgSrc=APPLICATION_PATH.'/resources/offline.png';
								break;
							case \Huhu\Library\User::$USER_STATUS_AWAY:
								$statusImgSrc=APPLICATION_PATH.'/resources/away.png';
								break;
						}
					} else {
						$statusImgSrc=APPLICATION_PATH.'/resources/inactive.png';
					}
					
					$statusImgObj=null;
					$statusImgSize=null;
					if ($statusImgSrc) {
						$statusImgObj=imagecreatefrompng($statusImgSrc);
						$statusImgSize=getimagesize($statusImgSrc);
					}
					
					if ($statusImgObj) {
						$targetPixels=$big?64:20;
						$posX=$targetPixels-$statusImgSize[0]-1;
						$posY=$targetPixels-$statusImgSize[1]-1;
						$imgObjNew=imagecreatetruecolor($targetPixels, $targetPixels);
						$color=imagecolorallocate($imgObjNew, 245, 246, 246);
						imagefill($imgObjNew, 0,0, $color);
						
						
						if (imagecopy($imgObjNew, $imgObj, 0, 0, 0, 0, $imgSize[0], $imgSize[1])
						 && imagecopy($imgObjNew, $statusImgObj, $posX, $posY, 0, 0, $statusImgSize[0], $statusImgSize[1])) {
							// export pic
							ob_start();
							imagejpeg($imgObjNew, null, 100);
							$pic=ob_get_clean();
						}
					}
				}
			}
			
			$mc->save($pic, $mcKey);
		}
		
		
		
		
		if ($dataOnly) {
		    return base64_encode($pic);
		}
		
		$pic='<img src="data:image/jpg;base64,'.base64_encode($pic).'" alt="User pic" />';
		
		return $pic;
	}
	
	
	/**
	 * Generates a login hash from user and password to store on phone or cookies
	 * @param string $user
	 * @param string $password
	 * @return string hash
	 */
	public static function encryptLoginHash($user, $password) {
		$key = pack('H*', self::$_loginHashSalt);
		
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $user.':'.$password, MCRYPT_MODE_CFB, str_pad('0', 32)));
	}
	
	
	/**
	 * Decrypts the login hash and returns user and password
	 * @param string $hash
	 * @return Array
	 */
	public static function decryptLoginHash($hash) {
		$key = pack('H*', self::$_loginHashSalt);
		
		
		$output=rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, base64_decode($hash), MCRYPT_MODE_CFB, str_pad('0', 32)));
		if ($output) {
			$expl=explode(':', $output);
			
			if (count($expl)==2) {
				return Array('user' => $expl[0], 'password' => $expl[1]);
			}
		}
		return FALSE;
	}
	
	

	
	
	/**
	 * Returns the list of users this user is invisible for
	 * @return Array
	 */
	public static function getInvisibleForList() {
		$currentUser=\Zend_Registry::get('loggedinuser');
		$db=\Zend_Registry::get('Zend_Db');
		$mc=\Zend_Registry::get('Zend_Cache');
		
		$invisibleFor=$mc->load(\Huhu\Library\MemcacheManager::getKeyUserInvisibleFor($currentUser['id']));
		if ($invisibleFor===FALSE) {
			$res=$db->query("SELECT u.id, u.user AS name FROM user_invisible AS ui
							 INNER JOIN users AS u ON u.id = ui.fk_userInvisibleForId
							 WHERE ui.fk_userId=".(int)$currentUser['id']);
			if ($res) {
				$invisibleFor=$res->fetchAll(\Zend_Db::FETCH_ASSOC);
				$mc->save($invisibleFor, \Huhu\Library\MemcacheManager::getKeyUserInvisibleFor($currentUser['id']));
			}
		}
		
		return $invisibleFor;
	}
	
	
	/**
	 * Adds a user to the invisible list
	 * @param int $userId
	 * @return boolean
	 */
	public static function addUserToInvisibleList($userId) {
		$currentUser=\Zend_Registry::get('loggedinuser');
		$db=\Zend_Registry::get('Zend_Db');
		
		$stmt=$db->prepare("INSERT INTO user_invisible (fk_userId, fk_userInvisibleForId) VALUES (?, ?);");
		if ($stmt->execute(Array($currentUser['id'], $userId))) {
			\Huhu\Library\MemcacheManager::invalidateOnUserStatusUpdate($currentUser['id']);
			return TRUE;
		}
		
		return FALSE;
	}
	
	
	/**
	 * Removes a user from the invisible list
	 * @param int $userId
	 * @return boolean
	 */
	public static function removeUserFromInvisibleList($userId) {
		$currentUser=\Zend_Registry::get('loggedinuser');
		$db=\Zend_Registry::get('Zend_Db');
	
		$stmt=$db->prepare("DELETE FROM user_invisible WHERE fk_userId=? AND fk_userInvisibleForId=?;");
		if ($stmt->execute(Array($currentUser['id'], $userId))) {
			\Huhu\Library\MemcacheManager::invalidateOnUserStatusUpdate($currentUser['id']);
			return TRUE;
		}
	
		return FALSE;
	}


  /**
   * Returns all users invisible to the currently logged in user
   * @return array
   * @throws \Zend_Exception
   */
  public static function getUsersInvisibleToMe() {
		$currentUser=\Zend_Registry::get('loggedinuser');
		$db=\Zend_Registry::get('Zend_Db');
		$mc=\Zend_Registry::get('Zend_Cache');
		
		$usersInvisibleToMe=$mc->load(\Huhu\Library\MemcacheManager::getKeyUserInvisibleToMe($currentUser['id']));
		if ($usersInvisibleToMe===FALSE) {
			$res=$db->query("SELECT fk_userId FROM user_invisible WHERE fk_userInvisibleForId=".(int)$currentUser['id']);
			if ($res) {
				$rows=$res->fetchAll(\Zend_Db::FETCH_ASSOC);
				$usersInvisibleToMe=Array();
				foreach ($rows as $row) {
					$usersInvisibleToMe[]=$row['fk_userId'];
				}
				$mc->save($usersInvisibleToMe, \Huhu\Library\MemcacheManager::getKeyUserInvisibleToMe($currentUser['id']));
			}			
		}	
		
		return $usersInvisibleToMe;
	}
	
	

	
	
}
<?php
/**
 * Contains the MemcacheManager class
 */

namespace Huhu\Library;

/**
 * Class \Huhu\Library\MemcacheManager
 * Contains helper methods for managing memcache
 *
 * Centralises the assembling of memcache keys, invalidation of special keys when special events occur etc.
 */
class MemcacheManager
{
	
	/**
   * @var Array Contains all the memcache keys grouped by the event when to invalidate them
	 * Format:
	 * 	'actionkey' => Array(
	 * 		'memcache_key_with_placeholders' => Query to select placeholder values (can contain pdo placeholders)
	 */
	private static $_mcKeys=Array(
			'statusupdate' => Array(
				'contactlist_##userid##' 			=> Array(
														'mcsource' => Array('\Huhu\Library\MemcacheManager::getKeyContactlist', 'userid'), // callback
													   	),
				'contactlist_##fk_contactUserID##'  => Array(
														'sql' => 'SELECT fk_contactUserID, :param_userid AS param_userid FROM contactlist WHERE fk_ownerUserID=:userid',
													  	),
				'user_openchats_##users##' 			=> Array(
														'mcsource' => Array('\Huhu\Library\MemcacheManager::getKeyOpenChats', 'userid'),
														),
				'user_openchats_##fk_userID##' 		=> Array(
														'sql' => 'SELECT cu.fk_userID, :param_userid AS param_userid FROM chats AS c LEFT JOIN chats_user AS cu ON cu.fk_chatID = c.id WHERE c.fk_ownerUserID=:userid',
														),
				'active_chat_##userid##'			=> Array(), // only fixed source data (own userid)
				'user_app_in_background_##userid##' => Array(),
				'userpicturewithstatus_##param_userid##_##userid##_0'	
													=> Array(
														'mcsource' => Array('\Huhu\Library\MemcacheManager::getKeyContactlist', 'userid'), // callback
														),
				'userpicturewithstatus_##param_userid##_##userid##_1'
													=> Array(
														'mcsource' => Array('\Huhu\Library\MemcacheManager::getKeyContactlist', 'userid'), // callback
														),
						
				'userpicturewithstatus_##param_userid##_##fk_contactUserID##_0'  
													=> Array(
														'sql' => 'SELECT fk_contactUserID, :param_userid AS param_userid FROM contactlist WHERE fk_ownerUserID=:userid',
														),
				'userpicturewithstatus_##param_userid##_##fk_contactUserID##_1'
													=> Array(
														'sql' => 'SELECT fk_contactUserID, :param_userid AS param_userid FROM contactlist WHERE fk_ownerUserID=:userid',
														),
				'user_invisible_for_##userid##'     => Array(),
				'user_invisible_to_me_##userid##'   => Array(),
				'user_invisible_to_me_##fk_contactUserID##'
													=> Array(
														'sql' => 'SELECT fk_contactUserID, :param_userid AS param_userid FROM contactlist WHERE fk_ownerUserID=:userid',
													  	),
				'user_invisible_##userid##'			=> Array(),
        'userpushmethods_##userid##'    => Array(),
        'userdata_by_id_##userid##'     => Array(),
        'userdata_by_name_##user##'     => Array(
                                                  'sql' => 'SELECT `user`, :param_userid FROM users WHERE id=:userid',
                                                ),
			),

      'pushMethods' => Array(
        'userpushmethods_##userid##'    => Array(),
      ),
			
			
			'newmessage' => Array(
				'user_openchats_##fk_userID##' 		=> Array(
														'sql' => 'SELECT cu.fk_userID FROM chats AS c LEFT JOIN chats_user AS cu ON cu.fk_chatID = c.id WHERE c.id=:chatid',
														),
			),
			
			
			'chatuserchange' => Array(
				'user_openchats_##fk_userID##' 		=> Array(
														'sql' => 'SELECT cu.fk_userID FROM chats AS c LEFT JOIN chats_user AS cu ON cu.fk_chatID = c.id WHERE c.id=:chatid',
														),
			),
			
			
			'contactlistchange' => Array(
				'contactlist_##ownUserId##'			=> Array(), // only fixed source data (userids affected from the change)
				'contactlist_##contactUserId##'			=> Array(), // only fixed source data (userids affected from the change)
	 			'contactlist_openrequests_##ownUserId##' => Array(), // only fixed source data (userids affected from the change)
				'contactlist_openrequests_##contactUserId##' => Array(), // only fixed source data (userids affected from the change)
				'userpicturewithstatus_##ownUserId##_##contactUserId##_0'	=> Array(), // only fixed source data (own userid)
				'userpicturewithstatus_##ownUserId##_##contactUserId##_1'	=> Array(), // only fixed source data (own userid)
				'userpicturewithstatus_##contactUserId##_##ownUserId##_0'	=> Array(), // only fixed source data (own userid)
				'userpicturewithstatus_##contactUserId##_##ownUserId##_1'	=> Array(), // only fixed source data (own userid)
			),
			
			'userprofilechange' => Array(
					'contactlist_##userid##' 			=> Array(
															'mcsource' => Array('\Huhu\Library\MemcacheManager::getKeyContactlist', 'userid'), // callback
														   ),
					'contactlist_##fk_contactUserID##'  => Array(
															'sql' => 'SELECT fk_contactUserID, :param_userid AS param_userid FROM contactlist WHERE fk_ownerUserID=:userid',
														   ),
					'userpicturewithstatus_##param_userid##_##userid##_0'
														=> Array(
															'mcsource' => Array('\Huhu\Library\MemcacheManager::getKeyContactlist', 'userid'), // callback
														   ),
					'userpicturewithstatus_##param_userid##_##userid##_1'
														=> Array(
															'mcsource' => Array('\Huhu\Library\MemcacheManager::getKeyContactlist', 'userid'), // callback
														   ),
					'userpicturewithstatus_##param_userid##_##fk_contactUserID##_0'
														=> Array(
															'sql' => 'SELECT fk_contactUserID, :param_userid AS param_userid FROM contactlist WHERE fk_ownerUserID=:userid',
														   ),
					'userpicturewithstatus_##param_userid##_##fk_contactUserID##_1'
														=> Array(
															'sql' => 'SELECT fk_contactUserID, :param_userid AS param_userid FROM contactlist WHERE fk_ownerUserID=:userid',
														  ),
					'userpicture_##param_userid##_0'
														=> Array(
															'mcsource' => Array('\Huhu\Library\MemcacheManager::getKeyContactlist', 'userid'), // callback
														  ),
					'userpicture_##param_userid##_1'
														=> Array(
															'mcsource' => Array('\Huhu\Library\MemcacheManager::getKeyContactlist', 'userid'), // callback
														  ),
          'userdata_by_id_##param_userid##'
                            => Array(),
          'userdata_by_name_##user##'
                            => Array(
                              'sql' => 'SELECT `user`, :param_userid AS param_userid FROM users WHERE id=:userid',
                               ),
          'userpublickey_##param_userid##'
                            => Array(),


      ),
	);
	
	/**
	 * invalidate cache keys related to user status
	 * @param int $userid
	 */
	public static function invalidateOnUserStatusUpdate($userid) {
		/*
		 * betroffen:
		 * getKeyContactlist
		 * getKeyOpenChats
		 * 
		 * allerdings nicht nur für den aktuellen user, sondern für alle user die der aktuelle user
		 * - in seiner kontaktliste hat (im Falle von getKeycontactlist)
		 * - mit denen er einen chat offen hat (im Falle von getKeyOpenChats) 
		 * 
		 */
		
		// invalidate own cache
		self::_invalidate('statusupdate', Array('userid' => $userid), Array(Array('userid' => $userid, 'fk_userID' => $userid, 'param_userid' => $userid)));
		// invalidate others cache
		self::_invalidate('statusupdate', Array('userid' => $userid, 'param_userid' => $userid));
	}
	
	
	/**
	 * invalidates cache keys when new message to chat is pushed
	 * @param int $chatid
	 */
	public static function invalidateOnMessagePush($chatid) {
		// invalidate others cache
		self::_invalidate('newmessage', Array('chatid' => $chatid));
				
	}


  /**
   * invalidates cache keys when user adds push method
   * @param $userId
   */
  public static function invalidateOnPushMethodsChange($userId) {
    self::_invalidate('pushMethods', Array(), Array('userid' => $userId));
  }
	
	/**
	 * invalidates cache keys when users in a chat have changed
	 * @param int $chatid
	 */
	public static function invalidateOnChatUsersChange($chatid) {
		// invalidate others cache
		self::_invalidate('chatuserchange', Array('chatid' => $chatid));
	}
	
	
	/**
	 * invalidates cache keys when users profile changes
	 * @param int $userid
	 */
	public static function invalidateOnUserProfileChange($userid) {
		// invalidate own cache
		self::_invalidate('userprofilechange', Array('userid' => $userid), Array(Array('userid' => $userid, 'fk_userID' => $userid, 'param_userid' => $userid)));
		// invalidate others cache
		self::_invalidate('userprofilechange', Array('userid' => $userid, 'param_userid' => $userid));
	}
	
	
	/**
	 * invalidates cache keys when contactlist changes
	 * @param int $ownUserId
   * @param int $contactUserId
	 */
	public static function invalidateOnContactListChange($ownUserId, $contactUserId) {
		self::_invalidate('contactlistchange', null, Array(Array('ownUserId' => $ownUserId, 'contactUserId' => $contactUserId)));	
	}
	
	
	
	/**
	 * Invalidates all cache keys with this prefix and params
   * @param string $action
	 * @param array $sourceParams
	 * @param array $fixedSourceResults
	 */
	private static function _invalidate($action, $sourceParams=null, $fixedSourceResults=null) {
		$mc=\Zend_Registry::get('Zend_Cache');
		
		\Zend_Registry::get('Zend_Log')->info('========================================');
		\Zend_Registry::get('Zend_Log')->info('MemcacheManager: Start invalidating for action: '.$action);
		\Zend_Registry::get('Zend_Log')->info('========================================');
		
		$keysToInvalidate=Array();
		
		if (isset(self::$_mcKeys[$action])) {
			$actionData=self::$_mcKeys[$action];
			foreach ($actionData as $mcKey => $source) {
				if (!is_array($source) || !count($source)) {
					$source=Array('foo' => null);
				}
				foreach ($source as $sourceType => $query) {
					$sourceRows=null;
					if (is_array($fixedSourceResults)) {
						// we have fixed source rows

            $k=array_keys($fixedSourceResults);
            if (!is_array($fixedSourceResults[$k[0]])) {
              $fixedSourceResults=Array($fixedSourceResults);
            }

						$sourceRows=$fixedSourceResults;
					} else {
						// fetch source data
						switch ($sourceType) {
							case 'mcsource':
								$sourceRows=self::_getSourceDataMemcache($query, $sourceParams);
								break;
							case 'sql':
								$sourceRows=self::_getSourceDataDatabase($query, $sourceParams);
								break;
						}
					}


					if (is_array($sourceRows)) {
						foreach ($sourceRows as $row) {
							if (is_array($row)) {
								$mcKeyNew=$mcKey;
								foreach ($row as $field => $fieldVal) {
									if (!is_array($fieldVal)) {
										$fieldVal=Array($fieldVal=>1);
									}
									
									foreach ($fieldVal as $val => $foo) {
										if (is_scalar($val)) {
											
											\Zend_Registry::get('Zend_Log')->info('mckey, replace ##'.$field.'## with '.$val);

											$mcKeyNew=str_replace('##'.$field.'##', $val, $mcKeyNew);
										}
									}
								}
								$keysToInvalidate[$mcKeyNew]=1;
							}
						}
					}
				}
			}
		}
		

		\Zend_Registry::get('Zend_Log')->info('Keys to invalidate: '.print_r($keysToInvalidate, 1));
		
		foreach ($keysToInvalidate as $mcKey => $foo) {
			if (!strstr($mcKey, '##')) {
				$mc->remove($mcKey);
			}
		}
		
		
	}
	
	
	/**
	 * Get source data for receiving keys to invalidate from database
	 * @param string $query
   * @param array $params
   * @return array $sourceData
	 */
	private static function _getSourceDataDatabase($query, $params) {
		$db=\Zend_Registry::get('Zend_Db');
		
		$stmt=$db->prepare($query);
		if ($stmt->execute($params)) {
			return $stmt->fetchAll(\Zend_Db::FETCH_ASSOC);
		}
		return FALSE;
	}
	
	
	/**
	 * Get source data for receiving keys to invalidate from memcache
	 * @param Array $keyCallback
   * @param array $params
   * @return array $sourceData
	 */
	private static function _getSourceDataMemcache($keyCallback, $params) {
		$mc=\Zend_Registry::get('Zend_Cache');
		// check if callback exists in this method
		if (is_callable($keyCallback[0])) {
			$paramsForCallback=Array();
			foreach ($keyCallback as $i => $val) {
				if ($i > 0) {
					// is parameter the callback need, check if it is given
					if (array_key_exists($val, $params)) {
						$paramsForCallback[$val]=$params[$val];
					} else {
						return FALSE;
					}
				}
			}
			
			
			$mcKey=call_user_func_array($keyCallback[0], $paramsForCallback);
			return $mc->load($mcKey);
		}
		
		return FALSE;
	}
	
	
	
	/********************************************
	 * 					MEMCACHE KEYS			*
	 ********************************************/											
	
	/**
	 * Key under which the current contactlist of a user is stored
	 * @param int $userId
   * @return string $key
	 */
	public static function getKeyContactlist($userId) {
		return 'contactlist_'.$userId;
	}
	
	
	/**
	 * Key under which the file uploads were stored (type, size, status, chatid)
	 * @param string $uploadId
   * @return string $key
	 */
	public static function getKeyFileUpload($uploadId) {
		return 'fileupload_'.$uploadId;
	}
	
	
	/**
	 * Key under which the current open requests of a user is stored
	 * @param int $userId
   * @return string $key
	 */
	public static function getKeyOpenRequests($userId) {
		return 'contactlist_openrequests_'.$userId;
	}
	
	
	/**
	 * Key under which the last 20 messages of a chat are stored
	 * @param int $chatId
	 * @return string
	 */
	public static function getKeyChat($chatId) {
		return 'chat_'.$chatId;
	}


  /**
 * Key under which the last unique message id is stored
 * @param int $chatId
 * @return string
 */
  public static function getKeyChatMessageId($chatId) {
    return 'chat_unique_message_id_'.$chatId;
  }


	
	/**
	 * Key under which the currently open chats of a user are stored
	 * @param int $userId
   * @return string $key
	 */
	public static function getKeyOpenChats($userId) {
		return 'user_openchats_'.$userId;
	}
	
	
	/**
	 * Key under which the user name to a user id is stores
	 * @param int $userId
   * @return string $key
	 */
	public static function getKeyUserName($userId) {
		return 'username_'.$userId;
	}
	
	
	/**
	 * Key under which the message if of the last read message of a chat and a user is stored
	 * @param int $userid
	 * @param int $chatId
   * @return string $key
	 */
	public static function getKeyChatLastRead($userid, $chatId) {
		return 'chat_last_read_'.$userid.'_'.$chatId;
	}
	
	
	/**
	 * Key under which the currently active chatid of a user is stored
	 * @param int $userid
   * @return string $key
	 */
	public static function getKeyActiveChat($userid) {
		return 'active_chat_'.$userid;
	}
	
	


  /**
   * Key under which the userdata by id is stored
   * @param $userid
   * @return string
   */
  public static function getUserByID($userid) {
    return 'userdata_by_id_'.$userid;
  }


  /**
   * Key under which the userdata by name is stored
   * @param $username
   * @return string
   */
  public static function getUserByName($username) {
    return 'userdata_by_name_'.$username;
  }



  /**
   * Key under which the timestamp of the last received heartbeat is stored
   * @param $userid
   * @return string
   */
  public static function getKeyUserHeartbeat($userid) {
    return 'user_heartbeat_'.$userid;
  }

	
	/**
	 * Key under which the invisible flag of a user is stored
	 * @param int $userid
   * @return string $key
	 */
	public static function getKeyUserInvisible($userid) {
		return 'user_invisible_'.$userid;
	}
	
	
	/**
	 * Key under which the list of users is stored this user should be invisible for
	 * @param int $userid
   * @return string $key
	 */
	public static function getKeyUserInvisibleFor($userid) {
		return 'user_invisible_for_'.$userid;
	}


  /**
   * Key under which the list of users is stored which are invisible to currently logged in user
   * @param $userid
   * @return string
   */
  public static function getKeyUserInvisibleToMe($userid) {
		return 'user_invisible_to_me_'.$userid;
	}
	
	

	/**
	 * Key under which the active status of the user app is stored
	 * @param int $userid
   * @return string $key
	 */
	public static function getKeyAppInBackground($userid) {
		return 'user_app_in_background_'.$userid;
	}
	
	
	/**
	 * Key under which the last online status of a user is stored
	 * @param int $userid
   * @return string $key
	 */
	public static function getKeyUserLastOnlineStatus($userid) {
		return 'user_last_online_status_'.$userid;
	}
	
	/**
	 * Key under which the last online status of a user is stored (but used for pushing status change message)
	 * @param int $userid
   * @return string $key
	 */
	public static function getKeyUserLastOnlineStatusPush($userid) {
		return 'user_last_online_status2_'.$userid;
	}


  /**
   * Key under which the user picture (without status) of a user is saved
   * @param int $userid
   * @param bool|int $big
   * @return string $key
   */
	public static function getKeyUserPicture($userid, $big=FALSE) {
		return 'userpicture_'.$userid.'_'.(int)$big;
	}

  /**
   * Key under which the user picture (with status) of a user is saved
   * @param int $userid
   * @param int $currentUserId
   * @param bool|int $big
   * @return string $key
   */
	public static function getKeyUserPictureWithStatus($userid, $currentUserId, $big=FALSE) {
		return 'userpicturewithstatus_'.$userid.'_'.$currentUserId.'_'.(int)$big;
	}

  /**
   * Key under which the available user push methods were stored
   * @param $userid
   * @return string
   */
  public static function getKeyUserPushMethods($userid) {
    return 'userpushmethods_'.$userid;
  }


  /**
   * Key under which the public key of a user is stored
   * @param $userid
   * @return string
   */
  public static function getKeyUserPublicKey($userid) {
    return 'userpublickey_'.$userid;
  }
}
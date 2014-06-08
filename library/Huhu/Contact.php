<?php
/**
 * Contains the Contact class
 */

namespace Huhu\Library;

/**
 * Class \Huhu\Library\Contact
 * Contains helper methods for contacts
 */
class Contact
{
	
	/**
	 * Returns contactlist
	 * @param int $userId
   * @param bool $onlyAccepted
   * @return array $contactList
	 */
	public static function getContactList($userId, $onlyAccepted=FALSE) {
		$mc=\Zend_Registry::get('Zend_Cache');
		$contactList=$mc->load(\Huhu\Library\MemcacheManager::getKeyContactlist($userId));
    $translate=\Zend_Registry::get('Zend_Translate');


    if ($contactList===FALSE) {
			$db=\Zend_Registry::get('Zend_Db');
			$contactList=Array();
			 
			$stmt=$db->prepare("SELECT u.id, u.user, u.lastLogoutTimestamp, c.accepted FROM contactlist AS c
					INNER JOIN users AS u ON u.id = c.fk_contactUserID
					WHERE c.fk_ownerUserID = ?");
			if ($stmt->execute(Array($userId))) {
				$rows=$stmt->fetchAll(\Zend_Db::FETCH_ASSOC);

				foreach ($rows as $row) {
				    $sort='';
				    if ($row['accepted']==1) {
				    	$statusCode=\Huhu\Library\User::getOnlineStatus($row['id']);
				    	$status=\Huhu\Library\User::getOnlineStatus($row['id'], TRUE);
				    	if ($statusCode==\Huhu\Library\User::$USER_STATUS_ONLINE) {
				    		$sort='A'; // online users should be on top
				    	} else if ($statusCode==\Huhu\Library\User::$USER_STATUS_AWAY) {
				    		$sort='B'; // away users under online users
				    	} else {
				    		$sort='Z'; // offline users down to bottom
                $status.=' ('.$translate->_('Last online').': '.date('d.m.Y H:i:s', $row['lastLogoutTimestamp']).')';
				    	}
				    } else {
				        $status=$translate->_('Not authorized');
				    }
				    
				    $sort.=strtoupper(str_pad(substr($row['user'], 0, 4), 4, '0'));
				    
				    if (!$onlyAccepted || $row['accepted']) {
              $contactList[]=Array(
                  'id' 		=> $row['id'],
                  'userid'	=> $row['id'],
                  'name' 		=> $row['user'],
                  'status' 	=> $status,
                  'picture' 	=> \Huhu\Library\User::getUserPicture($row['id'], TRUE, TRUE),
                  'accepted'  => $row['accepted'],
                  'type'		=> $translate->_('Contacts'),
                  'isrequest' => 0,
                  'sort'      => $sort,
              );
				    }
				}
			}
			
			$mc->save($contactList, \Huhu\Library\MemcacheManager::getKeyContactlist($userId), array(), 300);
		}
		
		return $contactList;
	}
	

	/**
	 * Rerurns outstanding contact requests
	 * @param int $userId
   * @return array $openRequests
	 */
	public static function getOpenRequests($userId) {
    $translate=\Zend_Registry::get('Zend_Translate');

    $currentUser=\Zend_Registry::get('loggedinuser');
		$mc=\Zend_Registry::get('Zend_Cache');
		$openRequests=$mc->load(\Huhu\Library\MemcacheManager::getKeyOpenRequests($userId));

		if ($openRequests == FALSE) {
			$db=\Zend_Registry::get('Zend_Db');
			 
			$stmt=$db->prepare("SELECT u.user, u.id, c.id AS contactlist_id FROM contactlist AS c
					INNER JOIN users AS u ON u.id = c.fk_ownerUserID
					WHERE c.accepted=0 AND c.fk_contactUserID=?");
			if ($stmt->execute(array($currentUser['id']))) {
				$rows=$stmt->fetchAll(\Zend_Db::FETCH_ASSOC);
				foreach ($rows as $row) {
					$openRequests[]=Array(
							'contactlist_id' => $row['contactlist_id'],
							'name' => $row['user'],
							'picture' => \Huhu\Library\User::getUserPicture($row['id'], TRUE, TRUE),
							'type'		=> $translate->_('Not yet answered contact request'),
							'isrequest' => 1,
					        'status' => '',
					);
				}
				
				$mc->save($openRequests, \Huhu\Library\MemcacheManager::getKeyOpenRequests($userId));
			}
		}
		return $openRequests;
	}


	/**
	 * Adds a user to contactlist
	 * @param int $userId
	 * @param int $contactUserId
	 * @throws \Huhu\Library\Exception
   * @return bool $success
	 */
	public static function addContact($userId, $contactUserId) {
		$db=\Zend_Registry::get('Zend_Db');

			
			
		$stmt=$db->prepare("INSERT INTO contactlist (fk_ownerUserID, fk_contactUserID) VALUES (?,?)");
		if ($stmt->execute(Array($userId, $contactUserId))) {
			$stmt->closeCursor();
			\Huhu\Library\MemcacheManager::invalidateOnContactListChange($userId, $contactUserId);
			return TRUE;
		}
		
		return FALSE;
	}
	
	
	/**
	 * Accepts a contact request
	 * @param int $userId
	 * @param int $contactListId
	 * @throws \Huhu\Library\Exception
   * @return bool $success
	 */
	public static function acceptContact($userId, $contactListId) {
		$db=\Zend_Registry::get('Zend_Db');
		
		// get contact entry
		$contactEntry=null;
		$res=$db->query("SELECT * FROM contactlist WHERE id = ".(int)$contactListId);
		if ($res) {
			$contactEntry=$res->fetch(\Zend_Db::FETCH_ASSOC);
		} else {
			throw new \Huhu\Library\Exception('Invalid contactlist entry');
		}
		
		// set accepted flag
		$stmt=$db->prepare("UPDATE contactlist SET accepted=1 WHERE id = ?");
		if ($stmt->execute(Array($contactListId))) {
			$stmt->closeCursor();
			// add to own contactlist
			$stmt2=$db->prepare("INSERT INTO contactlist (fk_ownerUserID, fk_contactUserID, accepted) VALUES (?,?,?)");
			if ($stmt2->execute(Array($userId, $contactEntry['fk_ownerUserID'], 1))) {
				$stmt2->closeCursor();
				\Huhu\Library\MemcacheManager::invalidateOnContactListChange($userId, $contactEntry['fk_ownerUserID']);
				
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	
	
	/**
	 * Rejects a contact request
	 * @param int $userId
	 * @param int $contactListId
	 * @throws \Huhu\Library\Exception
   * @return bool $success
	 */
	public static function rejectContact($userId, $contactListId) {
	    $db=\Zend_Registry::get('Zend_Db');
	
	    // get contact entry
	    $contactEntry=null;
	    $res=$db->query("SELECT * FROM contactlist WHERE id = ".(int)$contactListId);
	    if ($res) {
	        $contactEntry=$res->fetch(\Zend_Db::FETCH_ASSOC);
	    } else {
	        throw new \Huhu\Library\Exception('Invalid contactlist entry');
	    }
	
	    // set accepted flag
	    $stmt=$db->prepare("DELETE FROM contactlist WHERE id = ?");
	    if ($stmt->execute(Array($contactListId))) {
	    	$stmt->closeCursor();
	    	\Huhu\Library\MemcacheManager::invalidateOnContactListChange($userId, $contactEntry['fk_ownerUserID']);
	    	
            return TRUE;
	    }
	
	    return FALSE;
	}
	
	/**
	 * Removes a contract from a list
	 * @param int $userId
	 * @param int $contactListId
	 * @throws \Huhu\Library\Exception
   * @return bool $success
	 */
	public static function removeContact($userId, $contactListId) {
		$db=\Zend_Registry::get('Zend_Db');
		 
		$contactListEntry=null;
		$res=$db->query("SELECT fk_ownerUserID, fk_contactUserID FROM contactlist WHERE id = ".(int)$contactListId);
		if ($res) {
			$contactListEntry=$res->fetch(\Zend_Db::FETCH_ASSOC);
		} else {
			throw new \Huhu\Library\Exception('Invalid contactlist entry');
		}
		 
		// delete from both lists
		$res1=$db->query("DELETE FROM contactlist WHERE id=".(int)$contactListId);
		$res2=$db->query("DELETE FROM contactlist WHERE fk_ownerUserID=".(int)$contactListEntry['fk_contactUserID']." AND fk_contactUserID=".(int)$userId);
		
		if ($res1 && $res2) {
			\Huhu\Library\MemcacheManager::invalidateOnContactListChange($userId, $contactListEntry['fk_contactUserID']);
				
			return TRUE;
		}
		
		return FALSE;
	}
	
	
	/**
	 * Searches for contracts
	 * @param string $userName
	 * @param array $additionalFields
   * @param bool $fromPhoneSync
   * @return array $results
	 */
	public static function search($userName='', $additionalFields=array(), $fromPhoneSync=FALSE) {
    $translate=\Zend_Registry::get('Zend_Translate');

		$fieldTranslations=Array(
				'email' => 'E-Mail',
				'city' => $translate->_('City'),
				'zip' => $translate->_('ZIP-Code'),
				'age' => $translate->_('Age'),
				'birthday' => $translate->_('Birthday'),
				'firstname' => $translate->_('Forename'),
				'lastname' => $translate->_('Surname'),
				'lastschool' => $translate->_('Last school'),
				'company' => $translate->_('Company'),
				'phone'	 => $translate->_('Phone no.'),
				'mobile' => $translate->_('Mobile no.'),
				'url' => $translate->_('Website'),
				'girlsname' => $translate->_('Maiden name'),
		);
		
		// some fields are not unique enough and we have to AND them...
		$andFields=Array(
			'city',
			'zip',
			'age',
			'birthday',
			'firstname',
			'lastschool',
			'company',
		);
		
		$replaceSeparatorFields=Array(
			'phone',
			'mobile',	
		);
		
		
		if (!is_array($additionalFields)) $additionalFields=Array();
		 
		 
		$currentUser=\Zend_Registry::get('loggedinuser');
		
		$params=Array();
		$where="";
		if (!empty($userName)) {
			$where.="u.user LIKE :username";
			$params[':username']='%'.$userName.'%';
		}
		 
		if (is_array($additionalFields)) {
			$first=true;
			$closebracket=false;
			foreach ($additionalFields as $field => $value) {
				$field=preg_replace('/[^a-z0-9_]/i', '', $field);
			
				if (is_array($value)) $value=implode(';', $value);
						
				if ($first && !empty($where)) {
					$where.=" AND (";
					$closebracket=true;
				}
				else if (!$first) {
					$where.=" OR ";
				}
				
				$whereValue='ua.value';
				if ($fromPhoneSync && in_array($field, $replaceSeparatorFields)) {
					$whereValue='REPLACE( ua.value,  ";",  "" )';
				}
						
				if ($field == 'age') {
					$where.="ua.field='birthday' AND ua.value BETWEEN :birthday_from AND :birthday_to";
					$params[':birthday_from']=date('Y-m-d', strtotime('now - '.($value+1).' years + 1 day'));
					$params[':birthday_to']=date('Y-m-d', strtotime('now - '.($value).' years'));
				} else {
					$where.="ua.field='".$field."' AND ".$whereValue." LIKE :".$field;
					$params[':'.$field]=$value;
				}
						
				$first=false;
			}
			if ($closebracket) $where.=")";
			
		}
		 
		
		if (!empty($where)) {
			$db=\Zend_Registry::get('Zend_Db');
				
			$stmt=$db->prepare("SELECT u.id, u.user, u.photo, c.id AS c_id, c.accepted, GROUP_CONCAT(ua.field) AS matched_fields, GROUP_CONCAT(ua.value) AS matched_values,
			    				(SELECT GROUP_CONCAT(ua2.field) FROM user_additional AS ua2 WHERE ua2.fk_userID = u.id AND ua2.field IN ('birthday', 'city', 'company', 'lastschool', 'url') GROUP BY ua2.fk_userID) AS other_additionals_fields,
			    				(SELECT GROUP_CONCAT(ua2.value) FROM user_additional AS ua2 WHERE ua2.fk_userID = u.id AND ua2.field IN ('birthday', 'city', 'company', 'lastschool', 'url') GROUP BY ua2.fk_userID) AS other_additionals_values
			    				FROM users AS u
    							LEFT JOIN user_additional AS ua ON ua.fk_userID = u.id
    							LEFT JOIN contactlist AS c ON c.fk_contactUserID = u.id AND c.fk_ownerUserID = ".(int)$currentUser['id']."
    							WHERE ".$where."
    							GROUP BY u.id LIMIT 100");
			
			

			
			if ($stmt->execute($params)) {
				$results=Array();
				 
				$rows=$stmt->fetchAll(\Zend_Db::FETCH_ASSOC);
		
				foreach ($rows as $row) {
					if ($row['id'] != $currentUser['id']) {
						
						// for the 'and-fields' check if all match or skip this resultrow...
						
						$additionalFieldsMatched=explode(',', $row['matched_fields']);
						$allIn=true;
						foreach ($additionalFields as $key => $value) {
							if (!empty($value) && !in_array($key, $additionalFieldsMatched)) {
								// field didn't match - if it's and AND-field we have lost here...
								if (in_array($key, $andFields)) {
									$allIn=false;
									break;
								}
							}
						}
						if (!$allIn) {
							continue; // next one please...
						}
						
						
						
						// calculate matching points
						$score=100;
						if (!empty($userName)) {
							$score-=levenshtein($userName, $row['user']);
						}
						if (is_array($additionalFields)) {
							$matchedFields=explode(',', $row['matched_fields']);
							$matchedValues=explode(',', $row['matched_values']);
		
							foreach ($additionalFields as $field => $value) {
								if (is_array($value)) $value=implode(',', $value);
								$fk=array_search($field, $matchedFields);
								$score-=levenshtein($value, $matchedValues[$fk]);
							}
						}
							
		
						if (empty($row['c_id'])) {
							$additional=array_combine(explode(',', $row['matched_fields']), explode(',', $row['matched_values']));
							$additionalStr='';
							foreach ($additional as $k => $v) {
								if (!empty($additionalStr)) $additionalStr.=', ';
								if ($k=='birthday') {
									$v=date('d.m.Y', strtotime($v));
								}
									
								$additionalStr.=$fieldTranslations[$k].': '.$v;
									
								if ($k=='birthday') {
									$additionalStr.=', '.$translate->_('Age').': '.floor((time() - strtotime($v)) / 60 / 60 / 24 /365).' '.$translate->_('Years');
								}
							}
		
							$otherAdditionals=array_combine(explode(',', $row['other_additionals_fields']), explode(',', $row['other_additionals_values']));
							foreach ($otherAdditionals as $k => $v) {
								if (!array_key_exists($k , $additional)) {
									if (!empty($additionalStr)) $additionalStr.=', ';
									if ($k=='birthday') {
										$v=date('d.m.Y', strtotime($v));
									}
										
									if (isset($fieldTranslations[$k])) {
										$additionalStr.=$fieldTranslations[$k].': '.$v;
											
										if ($k=='birthday') {
											$additionalStr.=', '-$translate->_('Age').': '.floor((time() - strtotime($v)) / 60 / 60 / 24 /365).' '.$translate->_('Years');
										}
									}
								}
							}
		
							$results[]=Array(
									'id' 		=> $row['id'],
									'name' 		=> $row['user'],
									'picture' 	=> \Huhu\Library\User::getUserPicture($row['id'], TRUE),
									'additionalInfo' => $additionalStr,
									'onList' 	=> (!empty($row['c_id'])?true:false),
									'accepted' 	=> $row['accepted'],
									'score'		=> $score,
							);
						}
					}
				}
				 
				// sort by score descending
				uasort($results, function($c1, $c2) {
					if ($c1['score'] < $c2['score']) return 1;
					elseif ($c1['score'] > $c2['score']) return -1;
					return 0;
				});
				
				return $results;
					 
					
			}
		} 
		
		return FALSE;
	}
	
	
}
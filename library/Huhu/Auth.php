<?php
/**
 * Contains the Auth Class.
 * @package Huhu\Library
 */

namespace Huhu\Library;

/**
 * Class Auth
 *
 * Manages the authentication in the current session
 */
class Auth
{
	/**
	 * Checks if a user is loggedin,
   * if not throw \Huhu\Library\Auth\Exception
   * if yes, fetch user data from database and store to Zend_Registry
   *
   * Should be called in each Controller_Action requiring a logged in user (all, except login/signup action)
   * @throws \Huhu\Library\Auth\Exception
	 */
	public static function auth() {
		// check if authenticated
		$auth = \Zend_Auth::getInstance();
		if ($auth->hasIdentity()) {
			// Identity exists; get it
			$identity = $auth->getIdentity();
					
			// get userid
			$db=\Zend_Registry::get('Zend_Db');
			$stmt=$db->prepare("SELECT id, user, lastLoginTimestamp, email, UNCOMPRESS(`public_key`) AS public_key FROM users WHERE user=?");
			if ($stmt->execute(Array($identity))) {
				$row=$stmt->fetch(\Zend_Db::FETCH_ASSOC);
				$stmt->closeCursor();
				
				\Zend_Registry::set('loggedinuser', $row);
			}
		} else {
			throw new \Huhu\Library\Auth\Exception();
		}

		
		
	}
}
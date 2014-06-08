<?php
/**
 * Contains the session class.
 */

namespace Huhu\Library;

/**
 * Class \Huhu\Library\Session
 * Instanciates and starts a Zend_Session with the options from our config
 */
class Session
{
  /**
   * Instanciates and starts a \Zend_Session with the options from our config
   * @throws \Zend_Exception
   * @throws \Zend_Session_Exception
   */
  public static function init() {

		session_register_shutdown();
		
		// session handler set to memcached in php config in php.ini
		
		// setup session
		\Zend_Session::setOptions(\Zend_Registry::get('Zend_Config')->session->toArray());
		\Zend_Session::start();
	}
	

	
}
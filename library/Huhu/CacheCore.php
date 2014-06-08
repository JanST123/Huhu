<?php
/**
 * Contains the CacheCore class
 */

namespace Huhu\Library;

/**
 * Class \Huhu\Library\CacheCore
 * Extends the \Zend_Cache_Core and encrypts cache data on save, and decrypts on load
 *
 * @deprecated Do we need it since we encrypt each message with public/private key??
 */
class CacheCore extends \Zend_Cache_Core
{
  /**
   * @var string The secret key for the encryption
   */
  private static $_key='afd492cbbaa118899feeddeef898abba';


	/**
	 * Log a message at the WARN (4) priority.
	 *
	 * @param string $message Message to log
   * @param int $priority log priority
	 * @throws \Zend_Cache_Exception
	 * @return void
	 */
	protected function _log($message, $priority = 4)
	{
		if (!$this->_options['logging']) {
			return;
		}

		$logger = \Zend_Registry::get('Zend_Log');
		$logger->log($_SERVER['REQUEST_URI'].': '.$message, $priority);
	}
	
	
	/**
	 * Load data and decrypt
   * @param string $id The Cache key
   * @param Boolean $doNotTestCacheValidity do-no-test-cache-validity
   * @param Boolean $doNotUnserialize do-not-unserialize
   * @return Mixed Data fetched from cache or FALSE if key not found
	 * @see \Zend_Cache_Core::load()
	 */
	public function load($id, $doNotTestCacheValidity = false, $doNotUnserialize=false) {
		$data=parent::load($id, $doNotTestCacheValidity, $doNotUnserialize);
		

		if ($data!==FALSE) {
			$data=$this->_decrypt($data);

			return $data;
		}
		return FALSE;
	}
	
	
	/**
	 * Save data and encrypt
   * @param mixed $data
   * @param string $id
   * @param array $tags
   * @param boolean $specificLifetime
   * @param int $priority
	 * @see \Zend_Cache_Core::save()
   * return boolean Success
	 */
	public function save($data, $id = null, $tags = array(), $specificLifetime = false, $priority = 8) {
		return parent::save($this->_encrypt($data), $id, $tags, $specificLifetime, $priority);
	}


  /**
   * Encrypt data
   * @param array $data
   * @return string encrypted string
   */
  private function _encrypt($data) {
		$key = pack('H*', self::$_key);
		
		return mcrypt_encrypt('cast-128', $key, serialize($data), MCRYPT_MODE_CFB, str_pad('0', 8));
	}

  /**
   * Decrypt data
   * @param string $data encrypted data
   * @return Array decrypted data
   */
  private function _decrypt($data) {
		$key = pack('H*', self::$_key);
		
		$ret=unserialize(rtrim(mcrypt_decrypt('cast-128', $key, $data, MCRYPT_MODE_CFB, str_pad('0', 8))));
		
		return $ret;
	}
	
}
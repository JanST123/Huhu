<?php
/**
 * Special Huhu\Auth Exception
 */

namespace Huhu\Library\Auth;

/**
 * Class Exception
 *
 * Special exception, thrown in DT_Auth class if authentication failed.
 */
class Exception extends \Exception {

  /**
   * Just sets the default exception message
   */
  public function __construct() {
    $translate=\Zend_Registry::get('Zend_Translate');
		parent::__construct($translate->_('Please login'));
	}
}
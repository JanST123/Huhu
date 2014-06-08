<?php
/**
 * Contains the Action class
 */

namespace Huhu\Library\Controller;

/**
 * Class Action
 * A special Action class, directly derived from Zend_Controller_Action
 *
 * Assignes the Zend_Translate instance to the view
 *
 */
class Action extends \Zend_Controller_Action {
  /**
   * @var \Zend_Translate instance of Zend_Translate
   */
  protected $translate;

  /**
   * Assignes the \Zend_Translate instance to the view
   * @throws \Zend_Exception
   */
  public function init() {

    $this->translate=\Zend_Registry::get('Zend_Translate');
    $this->view->translate=$this->translate;

    parent::init();
  }
}
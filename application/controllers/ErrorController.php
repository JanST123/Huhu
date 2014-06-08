<?php
/**
 * Contains the ErrorController
 */

/**
 * Class ErrorController
 *
 * Contains the error action, triggered everytime an error is throws
 */
class ErrorController extends \Huhu\Library\Controller\Action
{
  /**
   * @var array contains the context switch, to disable layout if request is an XHR request
   */
  public $ajaxable = array(
    'error' => Array('html'),
  );

  /**
   * Inits the error controller :)
   */
  public function init()
  {
    parent::init();

    /*
     * Zend_Controller_Action_Helper_AjaxContext creates context 'html' by
    * default and associates it with view script suffix 'ajax'. Using the
    * Zend_Controller_Action_Helper_AjaxContext context switching is only
    * attempted if HTTP request header "X-Requested-With" is set to
    * "XMLHttpRequest" (i.e. otherwise standard view script will be rendered).
    */
    $this->_helper->ajaxContext
      ->addActionContext('error', 'html')
      ->setHeader('html', 'Content-Type', 'application/json')
      ->initContext('html');
  }


  /**
   * the error action, triggered everytime an error is throws
   * @throws Zend_Controller_Response_Exception
   */
  public function errorAction()
  {
    $errors = $this->_getParam('error_handler');

    if (!$errors || !$errors instanceof ArrayObject) {
      $this->view->message = 'You have reached the error page';
      return;
    }

    switch ($errors->type) {
      case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
      case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
      case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
        // 404 error -- controller or action not found
        $this->getResponse()->setHttpResponseCode(404);
        $priority = Zend_Log::NOTICE;
        $this->view->message = 'Page not found';
        break;
      default:
        // application error
        if (!$errors->exception instanceOf \Huhu\Library\Auth\Exception) {
          $this->getResponse()->setHttpResponseCode(500);
        }

        if ($errors->exception instanceOf Zend_Exception) {
          mail('webmaster@we-hu.hu', 'Huhu Exception', print_r($errors->exception, 1), 'FROM: exception@we-hu.hu');
        }

        $priority = Zend_Log::CRIT;
        $this->view->message = 'Application error';
        break;
    }

    // Log exception, if logger available
    if ($log = $this->getLog()) {
      $log->log($this->view->message, $priority, $errors->exception);
      $log->log('Request Parameters', $priority, $errors->request->getParams());
    }

    // conditionally display exceptions
    if ($this->getInvokeArg('displayExceptions') == true) {
      $this->view->exception = $errors->exception;
    }

    $this->view->request = $errors->request;
  }


  public function testAction() {
    $chat=new Application_Model_Chat(8);
    print_r($chat);
  }


  /**
   * Returns the logger
   * @return Zend_Log
   */
  public function getLog()
  {
    $bootstrap = $this->getInvokeArg('bootstrap');
    if (!$bootstrap->hasResource('Log')) {
      return false;
    }
    $log = $bootstrap->getResource('Log');
    return $log;
  }


}


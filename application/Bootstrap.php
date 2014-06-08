<?php
/**
 * The Bootstrap file
 */

/**
 * Class Bootstrap
 * The Zend Bootstrap Script
 * Loads configuration, initializes Zend_Db, Zend_Cache, Zend_Config, Zend_Log and stores to Zend_Registry
 * Starts session
 */
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{

  /**
   * Sends the Access-Control-Headers everytime the request inits
   */
  protected function _initRequest()
	{
	
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods", "POST, GET, OPTIONS');
		header('Access-Control-Allow-Headers *');
	}


  /**
   * Loads configuration, initializes Zend_Db, Zend_Cache, Zend_Config, Zend_Log and stores to Zend_Registry
   * Starts session
   * @throws Zend_Exception
   * @throws Zend_Loader_Exception
   * @throws Zend_Db_Exception
   */
  protected function _initApplication() {
		// setup config
		Zend_Registry::set('Zend_Config', new Zend_Config_Ini(APPLICATION_PATH.'/configs/application.ini', APPLICATION_ENV));

    $this->_initAutoload();

		// setup session
		if (!defined('IS_CRONJOB')) {
			\Huhu\Library\Session::init();
		}
		
	
		// setup cache
		$cacheCore=new \Huhu\Library\CacheCore(Zend_Registry::get('Zend_Config')->memcache->frontend->toArray());
		
		$cache=Zend_Cache::factory($cacheCore,
				'memcached',
				Zend_Registry::get('Zend_Config')->memcache->frontend->toArray(),
				Zend_Registry::get('Zend_Config')->memcache->backend->toArray()
		);
		Zend_Registry::set('Zend_Cache', $cache);
	
		// setup database
		$db=Zend_Db::factory(Zend_Registry::get('Zend_Config')->database);	
		Zend_Registry::set('Zend_Db', $db);
	
		Zend_Db_Table_Abstract::setDefaultAdapter($db);
		
		
		// setup logger
		$writer = new Zend_Log_Writer_Db($db, 'log', array('priority' => 'priority', 'message' => 'message'));
		$logger=new Zend_Log($writer);
		Zend_Registry::set('Zend_Log', $logger);
	}


  /**
   * Inits Zend_Translate and stores the instance to the Zend_Registry
   * @throws Zend_Exception
   */
  protected function _initLang() {
    $locale=new Zend_Locale();

    $locale->setLocale('en_EN');

    Zend_Registry::set('Zend_Locale', $locale);

    $defaultlanguage = 'en';



    Zend_Translate::setCache(Zend_Registry::get('Zend_Cache'));

    $translate = new Zend_Translate(
      array(
        'adapter' => 'gettext',
        'content' => APPLICATION_PATH.'/../languages',
        'tag'     => 'translate',
        'scan' => Zend_Translate::LOCALE_FILENAME,
      )
    );



    if (!$translate->isAvailable($locale->getLanguage())) {
      // not available languages are rerouted to another language
      $translate->setLocale($defaultlanguage);
    }

    Zend_Registry::set('Zend_Translate', $translate);
  }

	
	
	/**
	 * Init autoloader
	 * @return Zend_Application_Module_Autoloader
	 */
	protected function _initAutoload()
	{
    ini_set('include_path', ini_get('include_path').':'.APPLICATION_PATH.'/../library/DT');

		$autoloader=Zend_Loader_Autoloader::getInstance();
		$autoloader->registerNamespace( 'DT_' );
    $autoloader->registerNamespace( 'Huhu_' ); // workaround to use PHP Namespaces with the Zend_Autoloader...


    // workaround to use PHP Namespaces with the Zend_Autoloader...
    $loader = function($className) {
      $className=str_replace('Huhu\\Library\\', '', $className);
      $className = str_replace('\\', '_', $className);
      Zend_Loader_Autoloader::autoload('Huhu_'.$className);
    };
    $autoloader->pushAutoloader($loader, 'Huhu\\Library\\');

		return $autoloader;
	}

  /**
   * Init's the doctype
   */
  protected function _initDoctype() {
		$this->bootstrap('view');
		$view=$this->getResource('view');
		$view->doctype('XHTML1_STRICT');
		$view->css=Array();
		$view->js=Array();
	
	
		$ajaxContext = new Zend_Controller_Action_Helper_AjaxContext();
		$ajaxContext->setHeader('html', 'Content-Type', 'application/json');
		Zend_Controller_Action_HelperBroker::addHelper($ajaxContext);
	}

}


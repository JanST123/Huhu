<?php
/**
 * Contains the Model\Base class
 */

namespace Huhu\Library\Model;

/**
 * Class Base
 * @package Huhu\Library\Model
 *
 * Basic Features of a model (constructor, accepting ID and loading all the data from the db)
 */
class Base {

  /**
   * @var string The database Table
   */
  protected $_table=null;

  /**
   * @var array Contains the fields, available in the database table.
   * DON'T FORGET TO ADD NEW FIELDS as "@property" and the getter/setter as "@method" TO THE CLASS DOC-BLOCK!!
   */
  protected $_properties=Array();

  /**
   * Constructs a new (What-ever-)Model, if id given loads the data from the database
   * @param int $id The unique id from the database table if you want to fetch data from database
   */
  public function __construct($id=null) {
    if ($id) {
      $db=\Zend_Registry::get('Zend_Db');

      $res=$db->query("SELECT * FROM ".$this->_table." WHERE id = ".(int)$id);
      if ($res) {
        $row=$res->fetch(\Zend_Db::FETCH_ASSOC);

        foreach ($row as $key => $val) {
          if (array_key_exists($key, $this->_properties)) {
            // we use the set methods, even if the most of them are magic methods and we could directly
            // set the data to the properties. But there could be some special setters doing something useful...
            call_user_func(Array($this, 'set'.ucfirst($key)), $val);
          }
        }
      }
    }
  }


  /**
   * Provides magic getter and setter methods
   * @param $name
   * @param $arguments
   * @throws \Huhu\Library\Exception
   * @return mixed
   */
  public function __call($name, $arguments) {
    if (strpos($name, 'get') === 0 || strpos($name, 'set') === 0) {

      $propTmp=substr($name, 3);
      $prop='';
      // transform camel case to underscore logic

      +++ blödsinn

      $lastWasUpcase=false;
      for ($i=0; $i<strlen($propTmp); $i++) {
        if ($i && !$lastWasUpcase && strtolower($propTmp[$i]) != $propTmp[$i]) {
          $lastWasUpcase=true;
          $prop.='_';
        } else {
          $lastWasUpcase=false;
        }
        $prop.=strtolower($propTmp[$i]);
      }

      if (array_key_exists($prop, $this->_properties)) {
        if (strpos($name, 'get') === 0) {
          return $this->_properties[$prop];
        } else {
          $this->_properties[$prop]=$arguments[0];
          return $this;
        }

      } else {
        throw new \Huhu\Library\Exception('Property '.$prop.' not available.');
      }

    } else {
      throw new \Huhu\Library\Exception('Method not available.');
    }
  }
}
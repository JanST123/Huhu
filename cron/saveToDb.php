#!/usr/bin/php
<?php 
/**
 * Saves all messages from memcache to db
 * Updates the last pull timestamps from memcache to db
 */

namespace Huhu;


define('IS_CRONJOB', TRUE);
 
// Define path to application directory
defined('APPLICATION_PATH')
|| define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
|| define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

set_include_path(implode(PATH_SEPARATOR, array(
  realpath(APPLICATION_PATH . '/../library'),
  realpath(APPLICATION_PATH . '/../vendor/zendframework/zendframework1/library'),
)));

/** Composer Autoloader */
require APPLICATION_PATH . '/../vendor/autoload.php';

/** Zend_Bootstap*/
ini_set('include_path', ini_get('include_path').':'.APPLICATION_PATH.'/../library/DT');

require_once 'Zend/Loader/Autoloader.php';
$autoloader=\Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace( 'DT_' );
$autoloader->registerNamespace( 'Huhu_' ); // workaround to use PHP Namespaces with the Zend_Autoloader...
// workaround to use PHP Namespaces with the Zend_Autoloader...
    $loader = function($className) {
      $className=str_replace('Huhu\\Library\\', '', $className);
      $className = str_replace('\\', '_', $className);
      \Zend_Loader_Autoloader::autoload('Huhu_'.$className);
    };
    $autoloader->pushAutoloader($loader, 'Huhu\\Library\\');

$config=new \Zend_Config_Ini(APPLICATION_PATH.'/configs/application.ini', APPLICATION_ENV);
$db=\Zend_Db::factory($config->database);

$cacheCore=new \Huhu\Library\CacheCore($config->memcache->frontend->toArray());


$mc=\Zend_Cache::factory($cacheCore,
		'memcached',
		$config->memcache->frontend->toArray(),
		$config->memcache->backend->toArray()
);

\Zend_Registry::set('Zend_Cache', $mc);
\Zend_Registry::set('Zend_Db', $db);
\Zend_Registry::set('Zend_Config', $config);






// save messages -> get all open chats
$res=$db->query("SELECT c.id FROM chats AS c");
if ($res) {
	$chats=Array();
	
	$rows=$res->fetchAll(\Zend_Db::FETCH_ASSOC);
	foreach ($rows as $row) {

    // select the last message id
    $res2=$db->query("SELECT message_id FROM chats_messages WHERE fk_chatID = ".(int)$row['id']." ORDER BY message_id DESC LIMIT 1");
    $row2=$res2->fetch(\Zend_Db::FETCH_ASSOC);

	  $chats[$row['id']]=$row2['message_id'];
	}
	
	
	// now for each found chat, check new messages in memcache
	$updates=Array();
	foreach ($chats as $chatId => $lastMessageIdInDb) {
		$newMessages=\Huhu\Library\Chat::getMessages($chatId, $lastMessageIdInDb);

		$updated=false;
		if (is_array($newMessages)) {
			foreach ($newMessages as $newMsg) {
  			$updated=true;
				$updates[]="INSERT IGNORE INTO chats_messages (message_id, fk_chatID, fk_userID, fk_recipientUserID, timestamp, message) VALUES (".$db->quote($newMsg['message_id']).", ".(int)$chatId.", ".(int)$newMsg['user_id'].", ".(int)$newMsg['recipient_id'].", ".$db->quote($newMsg['timestamp']).", ".$db->quote($newMsg['message']).");";
			}
		}
		if ($updated) {
			// update chat timestamp
			$updates[]="UPDATE chats SET timestamp=NOW() WHERE id = ".(int)$chatId.";";
		}
	}
	
	if (count($updates)) {
		$db->query(implode("\n", $updates));
	}

  // if we came to here we stored the messages in db successful, so clean up memcache
  $_maxMessagesInStore=(int)\Zend_Registry::get('Zend_Config')->chat->maxMessagesInStore;
  foreach ($chats as $chatId => $lastMessageIdInDb) {
    $mcChat=$mc->load(\Huhu\Library\MemcacheManager::getKeyChat($chatId));
    $mcChat = array_slice($mcChat, ($_maxMessagesInStore * -1), $_maxMessagesInStore);

    // save back to memcache
    $mc->save($mcChat, \Huhu\Library\MemcacheManager::getKeyChat($chatId));
  }



  // for each open chat_user update the last read message if
  $res3=$db->query("SELECT fk_chatID, fk_userID, last_read_message_id FROM chats_user");
  $rows=$res3->fetchAll(\Zend_Db::FETCH_ASSOC);
  foreach ($rows as $row) {
    $messageId=$mc->load(\Huhu\Library\MemcacheManager::getKeyChatLastRead($row['fk_userID'], $row['fk_chatID']));
    if ($messageId && $messageId!=$row['last_read_message_id']) {
      $stmt=$db->prepare("UPDATE chats_user SET last_read_message_id=? WHERE fk_chatID=? AND fk_userID=?");
      $stmt->execute(Array(
          $messageId,
          $row['fk_chatID'],
          $row['fk_userID'],
        ));

    }
  }


}




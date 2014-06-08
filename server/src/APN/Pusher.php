<?php
/**
 * Created by PhpStorm.
 * User: jst
 * Date: 11.03.14
 * Time: 08:26
 */

namespace APN;

class APN_Pusher
{
    /**
     * @var ApnsPHP_Push_Server
     */
    private $_server;

    private $_occuredErrors=Array();


    /**
     * Called if zmq throws an error
     * @param $e
     */
    public function onZMQError($e)
    {
        $this->_debug('ZMQ ERROR: ' . print_r($e, 1));
        $this->_errorOccured($e);
    }

    /**
     * Called from zeroMQ if message from API arrives
     * @param $data
     */
    public function onApiMessage($data)
    {
        $message = unserialize($data);

        if (is_array($message) && isset($message['action'])) {
            switch ($message['action']) {
                case 'send':
                    $apnMessage = new ApnsPHP_Message($message['receiver']);
                    $apnMessage->setText((isset($message['data']['message']) ? $message['data']['message'] : ''));
//                  $message->setSound(); // @TODO: want we?
                    $apnMessage->setBadge($message['msgcnt']);
                    $apnMessage->setCustomProperty('data', $message['data']);

                    $this->_server->add($apnMessage);
                    break;

                default:
                    $this->_debug('Unknown Action ' . $message['action']);
                    break;
            }
        }
    }

    /**
     * Called if a new process was forked. Stores the process id to a file to be able to kill
     * all the subprocesses by init.d script (apn_server_kill.php)
     * @param $newProcessId
     */
    public function onNewFork($newProcessId)
    {
        $this->_debug('onNewForkCallback: ' . $newProcessId);

        $pids = Array();
        $filename = sys_get_temp_dir() . '/apn_pusher_pids';

        if (file_exists($filename)) {
            $pids = explode(',', file_get_contents($filename));
        }

        $pids[] = $newProcessId;

        file_put_contents($filename, implode(',', $pids));
    }


    /**
     * Inits the eventloop, instantiates server etc.
     * @param $loop
     */
    public function initEventLoop($loop)
    {
        $crtDir = dirname(__FILE__) . '/../../apn_crt/';

        // Instanciate a new ApnsPHP_Push object
        $this->_server = new ApnsPHP_Push_Server(
          ApnsPHP_Abstract::ENVIRONMENT_SANDBOX,
          $crtDir . 'apn_push_dev.pem'
        );

//      Set the Root Certificate Autority to verify the Apple remote peer
        $this->_server->setRootCertificationAuthority($crtDir . 'entrust_2048_ca.cer');

//      Set the number of concurrent processes
        $this->_server->setProcesses(2);

//      Starts the server forking the new processes
        $this->_server->start(Array($this, 'onNewFork'));


        $loop->addPeriodicTimer(10, Array($this, 'checkErrorQueue'));
    }


    /**
     * periodically checks the apn's server error queue
     */
    public function checkErrorQueue()
    {
        // This is a good place to dispatch signals...
        pcntl_signal_dispatch();

        // Check the error queue
        $aErrorQueue = $this->_server->getErrors();
        if (!empty($aErrorQueue)) {
            // Do somethings with this error messages...
            var_dump($aErrorQueue);
            $this->_debug('APN PUSH Server Errors in QUEUE! ' . print_r($aErrorQueue, 1));
            $this->_errorOccured($aErrorQueue);
        }
    }


    /**
     * Is called when new errors arrived
     * Sends email if max error count is reached
     * @param $errors
     */
    private function _errorOccured($errors) {
        $maxErrors=50;

        if (!is_array($errors)) $errors=Array($errors);

        $this->_occuredErrors=array_merge($errors, $this->_occuredErrors);

        if (count($this->_occuredErrors) > $maxErrors) {
            mail('webmaster@we-hu.hu', 'APN Push Server, more than '.$maxErrors.' Errors occured', print_r($this->_occuredErrors, 1), 'FROM: noreply@we-hu.hu');
            $this->_occuredErrors=Array();
        }
    }


    private function _debug($text)
    {
        file_put_contents('/tmp/huhu_apnpusher.log', "\n" . date('d.m.Y H:i:s') . " " . $text, FILE_APPEND);
        echo "\n" . date('d.m.Y H:i:s') . " " . $text;
    }

}


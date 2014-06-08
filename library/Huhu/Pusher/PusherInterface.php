<?php
/**
 * Contains the Pusher Interface
 */

namespace Huhu\Library\Pusher;

/**
 * Interface \Huhu\Library\Pusher_Interface
 */
interface PusherInterface {

  /**
   * Push the message
   * @param Array $data Data which should be pushed to the client. Should be assembled thru the @see \Huhu\Library\Pusher assembleXXX Methods
   * @return mixed
   */
  public function push($data);
}
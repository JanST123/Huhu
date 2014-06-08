#!/usr/bin/php
<?php
/**
 * Kills all running APN Server instances
 */
$filename=sys_get_temp_dir() .'/apn_pusher_pids';


if (file_exists($filename)) {
  $pids=explode(',', file_get_contents($filename));

  foreach ($pids as $pid) {
    posix_kill($pid, SIGKILL);
    debug("killing ".$pid);
  }

  unlink($filename);
} else {
  debug('No file...');
}



function debug($text) {
  file_put_contents('/tmp/huhu_apnpusher.log', "\n".date('d.m.Y H:i:s')." ".$text, FILE_APPEND);
  echo "\n".date('d.m.Y H:i:s')." ".$text;
}
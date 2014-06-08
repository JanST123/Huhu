<?php
/**
 * Contains the Date class
 */

namespace Huhu\Library;

/**
 * Class \Huhu\Library\Date
 * Contains date helpers
 */
class Date
{
  /**
   * Returns date and time (but date only if not today);
   * @param int $timestamp unix timestamp
   * @return bool|string
   */
	public static function getSmartDate($timestamp) {
	    $dateNow=date('Y-m-d');
	    $date=date('Y-m-d', $timestamp);
	    if ($date==$dateNow) {
	        return date('H:i:s', $timestamp);
	    }
	    return date('d.m.Y H:i:s', $timestamp);
	}
}
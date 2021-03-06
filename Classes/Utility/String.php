<?php
namespace ApacheSolrForTypo3\Solrgeo\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Phuong Doan <phuong.doan@dkd.de>, dkd Internet Service GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/


/**
 *
 *
 * @package solrgeo
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class String {

	/**
	 * Checks whether string ends with given string
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @return boolean
	 */
	static public function endsWith($haystack, $needle) {
		return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
	}

	/**
	 * Checks whether string contains another string
	 *
	 * @param $haystack
	 * @param $needle
	 * @return boolean
	 */
	static public function contains($haystack, $needle) {
		return strpos($haystack, $needle) !== false;
	}

} 

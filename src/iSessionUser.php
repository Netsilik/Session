<?php
namespace Netsilik\Session;

/**
 * @package Netsilik\Lib
 * @copyright (c) 2010-2016 Netsilik (http://netsilik.nl)
 * @license EUPL-1.1 (European Union Public Licence, v1.1)
 */

/**
 * The user object
 */
interface iSessionUser {
	
	/**
	 * Get the userId
	 * @return int The userId
	 */
	public function getUserId();
	
	/**
	 * Get the loginToken
	 * @return string The loginToken
	 */
	public function getLoginToken();
}
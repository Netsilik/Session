<?php
namespace Netsilik\Session;

/**
 * @package Netsilik\Session
 * @copyright (c) 2010-2016 Netsilik (http://netsilik.nl)
 * @license EUPL-1.1 (European Union Public Licence, v1.1)
 */

use Netsilik\Lib\Enum;
 
/**
 * The possible session states
 */
final class SessionState extends Enum {
	protected $enum = [
	    'failed'
	  , 'none'
	  , 'ok'
	  , 'recoverable'
	];
}

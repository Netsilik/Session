<?php
namespace Netsilik\Session\Entities;

/**
 * @package       Scepino/Session
 * @copyright (c) Scepino (http://scepino.com)
 * @license       EUPL-1.1 (European Union Public Licence, v1.1)
 */

use Netsilik\Lib\Enum;


class SessionState extends Enum {
	protected $_enum = [
	    'failed'
	  , 'none'
	  , 'ok'
	  , 'recoverable'
	];
}

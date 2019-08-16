<?php
namespace Tests\Helpers\Session;

/**
 * @copyright (c) Scepino (http://scepino.com)
 * @license       EUPL-1.1 (European Union Public Licence, v1.1)
 */

use Netsilik\Session\Session;
use Netsilik\Cookies\Interfaces\iCookies;
use Tests\BaseTestCase;


class GetStateTest extends BaseTestCase
{
	public function test_getStateCalled_SessionStateObjectInstanceReturned()
	{
		$mCookies = self::createMock(iCookies::class);
		
		$session  = new Session($mCookies, [
			'timeTillReAuth' => 20,  // Time (in minutes) until the user is required to reauthenticate themselves
			'timeToLive'     => 120, // Time (in minutes) until session and associated data is destroyed on the server side
			'cookie'         => [
				'name'   => 'sid', // The name of the cookie
				'path'   => '/',   // The cookie path
				'https'  => true, // Should the cookie ONLY be send back to the server over TLS secured connections
        		'domain' => 'localhost',
			],
		]);

		$result = $session->getState();

		self::assertEquals('none', $result);
	}
}

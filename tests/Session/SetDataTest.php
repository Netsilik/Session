<?php
namespace Tests\Helpers\Session;

/**
 * @copyright (c) Scepino (http://scepino.com)
 * @license       EUPL-1.1 (European Union Public Licence, v1.1)
 */

use ErrorException;
use Netsilik\Cookies\Interfaces\iCookies;
use Netsilik\Session\Session;
use Tests\BaseTestCase;


class SetDataTest extends BaseTestCase
{
	public function test_whenSessionNotStarted_thenFatalErrorTriggered()
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

		$this->expectException(ErrorException::class);
		$this->expectExceptionMessage('Cannot set session data for non-initialised session');

		$session->setData('foo', 123);
	}

	public function test_whenSessionStarted_thenValueIsSet()
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

		$session->create(null);

		$session->setData('foo', 123);

		$result = unserialize($_SESSION['app']['foo']);

		self::assertEquals(123, $result);
	}
}

<?php
namespace Tests\Helpers\Session;

/**
 * @copyright (c) Scepino (http://scepino.com)
 * @license       EUPL-1.1 (European Union Public Licence, v1.1)
 */

use Netsilik\Cookies\Interfaces\iCookies;
use Netsilik\Session\Session;
use Netsilik\Testing\Helpers\FunctionOverwrites;
use Tests\BaseTestCase;


class RecoverTest extends BaseTestCase
{
	public function test_whenNotInRecoverableState_thenFalseReturned()
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

		$result = $session->recover();

		self::assertFalse($result);
	}

	public function test_whenInRecoverableState_thenTrueReturned()
	{
		$mCookies = self::createMock(iCookies::class);
		$mCookies->method('get')->willReturn('abcdef0123456789');
		
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
		
		FunctionOverwrites::setActive('session_start', true); // Make sure $_SESSION does not get overwritten

		$_SESSION = ['session' => [
			'timestamp' => serialize(time() - 25 * 60), // 25 minutes old
			'userId' => serialize(123), // Known user
		]];

		$session->continue();
		$result = $session->recover();

		self::assertTrue($result);
	}
}

<?php
namespace Tests\Helpers\Session;

/**
 * @copyright (c) Scepino (http://scepino.com)
 * @license       EUPL-1.1 (European Union Public Licence, v1.1)
 */

use Netsilik\Session\Session;
use Netsilik\Cookies\Interfaces\iCookies;
use Netsilik\Testing\Helpers\FunctionOverwrites;
use Tests\BaseTestCase;


class ForceNewSessionIdTest extends BaseTestCase
{
	public function test_whenSessionNotStarted_thenFalseReturned()
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

		$result = $session->forceNewSessionId();

		self::assertFalse($result);
	}

	public function test_whenSessionIdCouldNotBeRegenerated_thenFalseReturned()
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

		FunctionOverwrites::setActive('session_regenerate_id', false, false);

		$session->create(null);
		$result = $session->forceNewSessionId();

		self::assertFalse($result);
	}

	public function test_whenSessionStartedAndNoErrors_thenTrueReturned()
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
		$result = $session->forceNewSessionId();

		self::assertTrue($result);
	}
}

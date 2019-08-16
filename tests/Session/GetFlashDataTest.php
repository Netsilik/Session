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


class GetFlashDataTest extends BaseTestCase
{
	public function test_whenSessionNotStarted_thenReturnNull()
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

		$_SESSION['flash']['foo'] = serialize(123);
		
		$result = $session->getFlashData('foo');

		self::assertNull($result);
	}

	public function test_whenSessionStarted_thenNoFlashAvailableAndDeletedFromGlobalSessionVariable()
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

		$_SESSION['flash']['foo'] = serialize(123);

		$session->create(null);
		
		$result = $session->getFlashData('foo');

		self::assertNull($result);
		
		self::assertArrayNotHasKey('flash', $_SESSION);
	}

	public function test_whenSessionContinued_thenFlashAvailableAndDeletedFromGlobalSessionVariable()
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

		$_SESSION = [
			'session' => [
				'timestamp' => serialize(time() - 15 * 60), // 15 minutes old
			],
			'flash' => [
				'foo' => serialize(123),
			],
		];

		$session->continue();
		
		$result = $session->getFlashData('foo');
		
		self::assertEquals(123, $result);
		self::assertArrayNotHasKey('flash', $_SESSION);
	}
}

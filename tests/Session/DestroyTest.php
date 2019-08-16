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


class DestroyTest extends BaseTestCase
{
	public function test_whenSessionDestroyed_thenSelfReturnedAndStateIsNone()
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

		$mCookies->expects(self::once())->method('delete')->with('sid'); // This is an actual assertion

		$result = $session->destroy();
		$state = $session->getState();

		self::assertEquals($session, $result);
		self::assertEquals('none', (string) $state);
	}

	public function test_whenSessionActive_thenSessionDestroyCalledOnce()
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

		$session->destroy();

		$callCount = FunctionOverwrites::getCallCount('session_destroy');

		self::assertEquals(1, $callCount);
	}
}

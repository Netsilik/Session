<?php
namespace Tests\Helpers\Session;

/**
 * @copyright (c) Scepino (http://scepino.com)
 * @license       EUPL-1.1 (European Union Public Licence, v1.1)
 */

use Netsilik\Session\Session;
use Netsilik\Cookies\Interfaces\iCookies;
use Tests\BaseTestCase;


class DeleteDataTest extends BaseTestCase
{
	public function test_whenSessionDataDeleted_thenDataNoLongerAvailableAndSelfReturned()
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

		$_SESSION['app']['foo'] = serialize(123);

		$result = $session->deleteData('foo');

		self::assertNotContains('foo', $_SESSION['app']);
		self::assertInstanceOf(Session::class, $result);
	}
}

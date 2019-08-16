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


class ContinueTest extends BaseTestCase
{
	public function test_whenSessionAlreadyStartedAndValid_thenTrueReturnedAndStateIsUnchanged()
	{
		$mCookies = self::createMock(iCookies::class);
		
		$session = new Session($mCookies, [
			'timeTillReAuth' => 20,
			// Time (in minutes) until the user is required to reauthenticate themselves
			'timeToLive'     => 120,
			// Time (in minutes) until session and associated data is destroyed on the server side
			'cookie'         => [
				'name' => 'sid', // The name of the cookie
				'path' => '/',   // The cookie path
				'https'  => true, // Should the cookie ONLY be send back to the server over TLS secured connections
				'domain' => 'localhost',
			],
		]);
		
		$session->create(null);
		$stateBefore = $session->getState();
		
		$result = $session->continue();
		$state  = $session->getState();
		
		self::assertTrue($result);
		self::assertEquals($stateBefore, $state);
	}
	
	public function test_whenSessionAlreadyStartedAndNotValid_thenFalseReturnedAndStateIsUnchanged()
	{
		$mCookies = self::createMock(iCookies::class);
		$mCookies->method('get')->willReturn('abcdef0123456789');
		

		
		$session = new Session($mCookies, [
			'timeTillReAuth' => 20,
			// Time (in minutes) until the user is required to reauthenticate themselves
			'timeToLive'     => 120,
			// Time (in minutes) until session and associated data is destroyed on the server side
			'cookie'         => [
				'name' => 'sid', // The name of the cookie
				'path' => '/',   // The cookie path
				'https'  => true, // Should the cookie ONLY be send back to the server over TLS secured connections
				'domain' => 'localhost',
			],
		]);
		
		$_SESSION = [];
		$session->continue(); // setup a state 'failed'
		$stateBefore = $session->getState();
		
		$result = $session->continue();
		$state  = $session->getState();
		
		self::assertFalse($result);
		self::assertEquals($stateBefore, $state);
	}
	
	public function test_whenNoSessionIdFound_thenFalseReturnedAndStateIsNone()
	{
		$mCookies = self::createMock(iCookies::class);
		
		$session = new Session($mCookies, [
			'timeTillReAuth' => 20,
			// Time (in minutes) until the user is required to reauthenticate themselves
			'timeToLive'     => 120,
			// Time (in minutes) until session and associated data is destroyed on the server side
			'cookie'         => [
				'name' => 'sid', // The name of the cookie
				'path' => '/',   // The cookie path
				'https'  => true, // Should the cookie ONLY be send back to the server over TLS secured connections
				'domain' => 'localhost',
			],
		]);
		
		$result = $session->continue();
		$state  = $session->getState();
		
		self::assertFalse($result);
		self::assertEquals('none', $state);
	}
	
	public function test_whenNoServerSideTimestampFound_thenFalseReturnedAndStateIsFailed()
	{
		$mCookies = self::createMock(iCookies::class);
		$mCookies->method('get')->willReturn('abcdef0123456789');
		
		$session = new Session($mCookies, [
			'timeTillReAuth' => 20,
			// Time (in minutes) until the user is required to reauthenticate themselves
			'timeToLive'     => 120,
			// Time (in minutes) until session and associated data is destroyed on the server side
			'cookie'         => [
				'name' => 'sid', // The name of the cookie
				'path' => '/',   // The cookie path
				'https'  => true, // Should the cookie ONLY be send back to the server over TLS secured connections
				'domain' => 'localhost',
			],
		]);
		
		$_SESSION = [];
		$result   = $session->continue();
		$state    = $session->getState();
		
		self::assertFalse($result);
		self::assertEquals('failed', (string) $state);
	}
	
	public function test_whenSessionTimedOutRecoverableForAnonymousUser_thenAutoRecover()
	{
		$mCookies = self::createMock(iCookies::class);
		$mCookies->method('get')->willReturn('abcdef0123456789');
		
		$session = new Session($mCookies, [
			'timeTillReAuth' => 20,
			// Time (in minutes) until the user is required to reauthenticate themselves
			'timeToLive'     => 120,
			// Time (in minutes) until session and associated data is destroyed on the server side
			'cookie'         => [
				'name' => 'sid', // The name of the cookie
				'path' => '/',   // The cookie path
				'https'  => true, // Should the cookie ONLY be send back to the server over TLS secured connections
				'domain' => 'localhost',
			],
		]);
		
		FunctionOverwrites::setActive('session_start', true); // Make sure $_SESSION does not get overwritten
		
		$_SESSION = [
			'session' => [
				'timestamp' => serialize(time() - 25 * 60), // 25 minutes old
				'userId'    => serialize(null), // Anonymous
			],
		];
		
		$result = $session->continue();
		$state  = $session->getState();
		
		self::assertTrue($result);
		self::assertEquals('ok', (string) $state);
	}
	
	public function test_whenSessionTimedOutRecoverableForUser_thenTrueReturnedAndStateIsRecoverable()
	{
		$mCookies = self::createMock(iCookies::class);
		$mCookies->method('get')->willReturn('abcdef0123456789');
		
		$session = new Session($mCookies, [
			'timeTillReAuth' => 20,
			// Time (in minutes) until the user is required to reauthenticate themselves
			'timeToLive'     => 120,
			// Time (in minutes) until session and associated data is destroyed on the server side
			'cookie'         => [
				'name' => 'sid', // The name of the cookie
				'path' => '/',   // The cookie path
				'https'  => true, // Should the cookie ONLY be send back to the server over TLS secured connections
				'domain' => 'localhost',
			],
		]);
		
		FunctionOverwrites::setActive('session_start', true); // Make sure $_SESSION does not get overwritten
		
		$_SESSION = [
			'session' => [
				'timestamp' => serialize(time() - 25 * 60), // 25 minutes old
				'userId'    => serialize(123), // Known user
			],
		];
		
		
		$result = $session->continue();
		$state  = $session->getState();
		
		self::assertTrue($result);
		self::assertEquals('recoverable', (string) $state);
	}
	
	public function test_whenSessionTimedOut_thenFalseReturnedAndStateIsFailed()
	{
		$mCookies = self::createMock(iCookies::class);
		$mCookies->method('get')->willReturn('abcdef0123456789');
		
		$session = new Session($mCookies, [
			'timeTillReAuth' => 20,
			// Time (in minutes) until the user is required to reauthenticate themselves
			'timeToLive'     => 120,
			// Time (in minutes) until session and associated data is destroyed on the server side
			'cookie'         => [
				'name' => 'sid', // The name of the cookie
				'path' => '/',   // The cookie path
				'https'  => true, // Should the cookie ONLY be send back to the server over TLS secured connections
				'domain' => 'localhost',
			],
		]);
		
		FunctionOverwrites::setActive('session_start', true); // Make sure $_SESSION does not get overwritten
		
		$_SESSION = [
			'session' => [
				'timestamp' => serialize(time() - 125 * 60), // 125 minutes old
			],
		];
		
		$result = $session->continue();
		$state  = $session->getState();
		
		self::assertFalse($result);
		self::assertEquals('failed', (string) $state);
	}
	
	public function test_whenSessionOk_thenTrueReturnedAndStateIsOk()
	{
		$mCookies = self::createMock(iCookies::class);
		$mCookies->method('get')->willReturn('abcdef0123456789');
		
		$session = new Session($mCookies, [
			'timeTillReAuth' => 20,
			// Time (in minutes) until the user is required to reauthenticate themselves
			'timeToLive'     => 120,
			// Time (in minutes) until session and associated data is destroyed on the server side
			'cookie'         => [
				'name' => 'sid', // The name of the cookie
				'path' => '/',   // The cookie path
				'https'  => true, // Should the cookie ONLY be send back to the server over TLS secured connections
				'domain' => 'localhost',
			],
		]);
		
		FunctionOverwrites::setActive('session_start', true); // Make sure $_SESSION does not get overwritten
		
		$_SESSION = [
			'session' => [
				'timestamp' => serialize(time() - 15 * 60), // 15 minutes old
			],
		];
		
		$result = $session->continue();
		$state  = $session->getState();
		
		self::assertTrue($result);
		self::assertEquals('ok', (string) $state);
	}
}

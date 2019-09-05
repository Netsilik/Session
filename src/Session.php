<?php
namespace Netsilik\Session;

/**
 * @package       Scepino/Session
 * @copyright (c) Scepino (http://scepino.com)
 * @license       EUPL-1.1 (European Union Public Licence, v1.1)
 */

use Netsilik\Cookies\Interfaces\iCookies;
use Netsilik\Session\Interfaces\iSession;
use Netsilik\Session\Entities\SessionState;


/**
 * Session helper
 */
class Session implements iSession
{
	/**
	 * The same-site policy for the session cookie
	 */
	const COOKIE_SAME_SITE = 'Lax';
	
	/**
	 * @var \Netsilik\Cookies\Interfaces\iCookies $_cookies The Cookies class instance
	 */
	private $_cookies = null;
	
	/**
	 * @var string $_cookieName The name policy for the session cookie
	 */
	private $_cookieName;
	
	/**
	 * @var string $_cookieDomain The domain for the session cookie
	 */
	private $_cookieDomain;
	
	/**
	 * @var string $_cookiePath The path for the session cookie
	 */
	private $_cookiePath;
	
	/**
	 * @var bool $_cookieSecure Indicate whether or not the cookie should only be send back to the server over secured connections
	 */
	private $_cookieSecure;
	
	/**
	 * @var string|null The contents of the Flash message
	 */
	private $_flashData;
	
	/**
	 * @var string $_sessionId The session identifier
	 */
	private $_sessionId;
	
	/**
	 * @var bool $_started Boolean flag to indicate if the session has been setup for this client
	 */
	private $_started = false;
	
	/**
	 * @var \Netsilik\Session\Entities\SessionState $_state The state of the session can be: 'failed', 'none', 'ok' or 'recoverable'
	 */
	private $_state;
	
	/**
	 * @var array $_stateCodes The list of state codes, used only for debugging, which allows tracing back where decisions on the session state were
	 *      made
	 */
	private $_stateCodes = [];
	
	/**
	 * @var int $_timeTillReAuth Time (in seconds) until the user is required to reauthenticate themselves
	 */
	private $_timeTillReAuth;
	
	/**
	 * @var int $_timeToLive Time (in seconds) until session and associated data is destroyed on the server side
	 */
	private $_timeToLive;
	
	/**
	 * @var int $_userId The Id of the user this session is bound to, if the session is not anonymous
	 */
	private $_userId = null;
	
	/**
	 * Constructor
	 *
	 * @param \Netsilik\Cookies\Interfaces\iCookies $cookies
	 * @param array                                $config
	 */
	public function __construct(iCookies $cookies, array $config)
	{
		$this->_cookies = $cookies;
		
		$this->_cookieName   = $config['cookie']['name'];
		$this->_cookiePath   = $config['cookie']['path'];
		$this->_cookieSecure = $config['cookie']['https'];
		$this->_cookieDomain = $config['cookie']['domain'];
		
		$this->_timeTillReAuth = $config['timeTillReAuth'] * 60; // convert to seconds
		$this->_timeToLive     = $config['timeToLive'] * 60;     // convert to seconds
		
		// Setup PHP's internal session handler
		ini_set('session.use_trans_sid', 0);
		ini_set('session.use_only_cookies', 1);
		ini_set('session.name', $this->_cookieName);
		ini_set('session.cache_limiter', null); // we will manage the cache control headers ourselfs, thank you
		ini_set('session.cache_expire', floor($this->_timeTillReAuth / 60)); // in minutes
		ini_set('session.gc_maxlifetime', $this->_timeToLive); // in seconds
		ini_set('session.sid_length', 32); // length of the sessionId, in characters
		ini_set('session.sid_bits_per_character', 4); // encode sessionId in 4 bits, using characters 0-9a-f
		
		// Setup initial state
		$this->_state = new SessionState('none');
		$this->_stateCodes[] = '__construct::ok';
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function getState() : string
	{
		return (string) $this->_state;
	}
	
	/**
	 * Get the stateCodes for this session, for debug purposes
	 *
	 * @return array An array with state codes
	 */
	public function getStateCodes() : array
	{
		return $this->_stateCodes;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function getUserInfo() : array
	{
		return [
			'userId' => $this->_userId,
		];
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function getUserId() : ?int
	{
		return $this->_userId;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function setUserId(?int $userId) : iSession
	{
		$this->_userId = $userId;
		$this->_setPrivateData('userId', $userId);
		
		return $this;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function create(?int $userId) : iSession
	{
		$this->destroy();
		
		session_start();
		session_regenerate_id(true);
		$this->_sessionId = session_id();
		$this->_started = true;
		
		$this->_setHeaders(); // Send the session cookie
		
		// Reinitialize session data
		$_SESSION = [];
		$this->setUserId($userId);
		$this->_setPrivateData('timestamp', time());
		$this->_initFlashData(); // Make sure we reset the flash data, if any
		
		$this->_state = new SessionState('ok');
		$this->_stateCodes[] = 'create::ok';
		
		return $this;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function continue() : bool
	{
		if ($this->_started) { // Session already initialized
			$this->_stateCodes[] = 'continue::already started';
			
			return ($this->_state == 'ok' || $this->_state == 'recoverable'); // return the same return value as before
		}
		
		if (!$this->_sessionCookieExists()) { // No valid sessionId found in cookie
			$this->_state = new SessionState('none');
			$this->_stateCodes[] = 'continue::no valid session cookie found';
			
			return false;
		}
		
		session_start();
		$this->_started   = true;
		$this->_sessionId = session_id();
		$this->_setHeaders();
		$this->_initFlashData(); // Make sure we get the flash data, if any
		
		$timestamp = $this->_getPrivateData('timestamp');
		
		if (null === $timestamp) { // We have no server-side timestamp information for this sessionId
			$this->_state = new SessionState('failed');
			$this->_stateCodes[] = 'continue::no server-side timestamp for session';
			
			return false;
		}
		
		$this->_userId = $this->_getPrivateData('userId');
		
		if ($timestamp + ($this->_timeTillReAuth) < time()) { // Session timed out
			if ($timestamp + ($this->_timeToLive) >= time()) { // Session still within recoverable timeframe
				if (null === $this->_userId) { // This is an anonymous session, auto recover
					$this->_setPrivateData('timestamp', time());
					$this->_state = new SessionState('ok');
					$this->_stateCodes[] = 'continue::anonymous session timed out, auto recover';
				} else { // This is a session bound to some user
					$this->_state = new SessionState('recoverable');
					$this->_stateCodes[] = 'continue::user-bound session timed out, recoverable';
				}
				
				return true;
			}
			
			// Session is too old to be recovered
			$this->_state        = new SessionState('failed');
			$this->_stateCodes[] = 'continue::session timed out, non-recoverable';
			
			return false;
		}
		
		// The session is Ok
		$this->_setPrivateData('timestamp', time());
		$this->_state        = new SessionState('ok');
		$this->_stateCodes[] = 'continue::session ok';
		
		return true;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function forceNewSessionId() : bool
	{
		if (!$this->_started) { // Session already initialized
			return false;
		}
		if (!session_regenerate_id(true)) {
			return false; // Could not regenerate the Id for some reason?
		}
		
		$this->_sessionId = session_id();
		$this->_setHeaders();
		
		return true;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function recover() : bool
	{
		if ($this->_state <> 'recoverable') {
			return false;
		}
		
		$this->_setPrivateData('timestamp', time());
		
		$this->_state = new SessionState('ok');
		$this->_stateCodes[] = 'recover::recovered successful';
		
		return true;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function destroy() : iSession
	{
		session_unset();
		if ($this->_started) {
			session_destroy();
			$this->_started = false;
		}
		
		header('Cache-Control:', true); // remove cache control header
		$this->_cookies->delete($this->_cookieName);
		$_SESSION     = [];
		$this->_state = new SessionState('none');
		
		return $this;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function setData(string $key, $value) : iSession
	{
		if (!$this->_started) {
			trigger_error('Cannot set session data for non-initialised session', E_USER_ERROR);
		}
		
		$_SESSION['app'][ $key ] = serialize($value);
		
		return $this;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function getData(string $key)
	{
		if (!isset($_SESSION['app'][ $key ])) {
			return null;
		}
		
		return unserialize($_SESSION['app'][ $key ]);
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function deleteData(string $key) : iSession
	{
		unset($_SESSION['app'][ $key ]);
		
		return $this;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function setFlashData(string $key, $value) : iSession
	{
		if (!$this->_started) {
			trigger_error('Cannot set session data for non-initialised session', E_USER_ERROR);
		}
		
		$_SESSION['flash'][ $key ] = serialize($value);
		
		return $this;
	}
	
	/**
	 * {@inheritDoc}
	 */
	public function getFlashData(string $key)
	{
		if (!isset($this->_flashData[ $key ])) {
			return null;
		}
		
		return unserialize($this->_flashData[ $key ]);
	}
	
	/**
	 * Read the flash data and delete it
	 *
	 * @return void
	 */
	private function _initFlashData() : void
	{
		$this->_flashData = [];
		
		if (isset($_SESSION['flash'])) {
			$this->_flashData = $_SESSION['flash'];
			unset($_SESSION['flash']);
		}
	}
	
	/**
	 * Set the session headers
	 *
	 * @return $this
	 */
	private function _setHeaders() : iSession
	{
		$this->_cookies->set($this->_cookieName, $this->_sessionId, null, $this->_cookiePath, $this->_cookieDomain, $this->_cookieSecure, true, self::COOKIE_SAME_SITE);
		header('Cache-Control: "private, max-age=0, must-revalidate"', true);
		header('Last-Modified: ' . date('D, d M Y H:i:s') . ' GMT', true);
		
		return $this;
	}
	
	/**
	 * @param string $key Same as Session::setData, only this function is used to manage session related data
	 * @param mixed  $value
	 *
	 * @return $this
	 */
	private function _setPrivateData(string $key, $value) : iSession
	{
		$_SESSION['session'][ $key ] = serialize($value);
		
		return $this;
	}
	
	/**
	 * Same as Session::getData, only this function is used to manage session related data
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	private function _getPrivateData(string $key)
	{
		if (!isset($_SESSION['session'][ $key ])) {
			return null;
		}
		
		return unserialize($_SESSION['session'][ $key ]);
	}
	
	/**
	 * Check if a valid session cookie exists
	 *
	 * @return bool true when a sessionId has been found, false otherwise
	 */
	private function _sessionCookieExists() : bool
	{
		if (null === ($sessionId = $this->_cookies->get($this->_cookieName))) {
			return false;
		}
		
		return ($sessionId === $this->_sanitizeSessionId($sessionId));
	}
	
	/**
	 * Make sure the sessionId is of expected length and only contains expected characters
	 * If non white listed characters are encountered, they will be discarded. If the string,
	 * after removing non-allowed characters, is not of the expected length, the string will be
	 * padded with leading zeros
	 *
	 * @param string $sessionId
	 *
	 * @return string
	 */
	private function _sanitizeSessionId(string $sessionId) : string
	{
		return str_pad(substr(preg_replace('/[^0-9a-f]/i', '', $sessionId), 0, 32), 32, 0, STR_PAD_LEFT);
	}
	
	/**
	 * Store session variables & unset object references
	 */
	public function __destruct()
	{
		session_write_close();
		
		$this->_cookies = null;
	}
}

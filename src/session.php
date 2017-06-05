<?php
namespace Netsilik\Session;

/**
 * @package Netsilik\Lib
 * @copyright (c) 2010-2016 Netsilik (http://netsilik.nl)
 * @license EUPL-1.1 (European Union Public Licence, v1.1)
 */

use Netsilik\Lib\Config;
use Netsilik\Lib\Cookies;

/**
 * Singleton (only 1 session / client possible)
 * Controls the session
 */
class Session {
	
	/**
	 * The path name of the session cookie
	 */
	const COOKIE_NAME = 'sid';
	
	/**
	 * The path for the session cookie
	 */
	const COOKIE_PATH = '/';
	
	/**
	 * The number of bits to be stored in each character when encoding the binary sessionId
	 */
	const HASH_BITS_PER_CHARACTER = 5;
	
	/**
	 * @var Session $instance The (static) instance of self (singleton pattern)
	 */
	private static $instance = null;
	
	/**
	 * @var Cookies $cookies The Cookies class instance
	 */
	private $cookies = null;
	
	/**
	 * @var bool $started Boolean flag to indicate if the session has been setup for this client
	 */
	private $started = false;
	
	/**
	 * @var string $responseCode A repsonse code string for a redirect-after-post
	 */
	private $responseCode = null;
	
	/**
	 * @var string $sessionId The session identifier
	 */
	private $sessionId = null;
	
	/**
	 * @var array $sessionData An array holding data stored for this client's session
	 */
	private $sessionData = array();
	
	/**
	 * @var Enum $state The state of the session can be: 'failed', 'none', 'ok' or 'recoverable'
	 */
	private $state = null;
	
	/**
	 * @var array $stateCode The list of state codes, used only for debugging, which allows tracing back where decisions on the session state were made
	 */
	private $stateCode = array();
	
	/**
	 * @var int $userId The Id of the user this session is bound to, if the session is not anonymous
	 */
	private $userId = null;
	
	/**
	 * Setup a new instance
	 */
	final private function __construct() {
		$config = Config::getInstance();
		$this->started = false;
		$this->sessionId = false;
		$this->responseCode = '';
		$this->stateCode = array();
		$this->sessionData = array();
		$this->config = Config::session();
		$this->cookies = Cookies::getInstance();
		$this->state = new SessionState('none');
		
		ini_set('session.use_trans_sid', 0);
		ini_set('session.use_only_cookies', 1);
		ini_set('session.name', self::COOKIE_NAME);
		ini_set('session.cache_expire', $this->config['timeTillReAuth']); 
		ini_set('session.gc_maxlifetime', $this->config['timeToLive'] * 60);
		ini_set('session.hash_bits_per_character', self::HASH_BITS_PER_CHARACTER);
	}
	
	/**
	 * private __clone: prevent object cloning
	 */
	final private function __clone() {
		// Intentionally left empty
	}
	
	/**
	 * Get the (static) instance of this object
	 * @return Object static instance of class Config
	 */
	public static function getInstance() {
		if (is_null(self::$instance)) {
			self::$instance = new static();
		}
		return self::$instance;
	}
	
	/**
	 * Get the current state of the session
	 * @return string describing the state
	 */
	public function getState() {
		return (string)$this->state;
	}
	
	/**
	 * Get the stateCodes for this session
	 * @return array with state codes
	 */
	public function getStateCode() {
		return $this->stateCode;
	}
	
	/**
	 * Get the user currently bound to this session
	 * @return array Associative array with the userId and loginToken keys
	 */
	public function getUserInfo() {
		return array('userId' => $this->getPrivateData('userId'), 'loginToken' => $this->getPrivateData('userLoginToken'));
	}
	
	/**
	 * @return mixed false if the user is not successfully authenticated, an instance of a user object implementing the iSessionUser if this session is bound to a user
	 */
	public function getUserId() {
		if (is_null($this->userId)) {
			return false;
		}
		return $this->userId;
	}
	
	public function setUserId(int $userId) {
		$this->userId = $userId;
		return true;
	}
	
	/**
	 * Create a new session
	 * @param int $userId The userId to bind this session to
	 * @return bool True
	 */
	public function create(int $userId = null) {
		$this->destroy();
		
		session_start();
		$this->started = true;
		session_regenerate_id(true);
		$this->sessionId = session_id();
		$this->setHeaders();
		$_SESSION = array();
		$this->claimSessionSuperGobal();
		
		if (is_null($userId)) {
			$this->setPrivateData('userId', 0);
			$this->setPrivateData('userLoginToken', 0);
			$this->setPrivateData('timestamp', time());
		} else {
			$this->userId = $userId;
			$this->setPrivateData('userId', $userId );
			$this->setPrivateData('timestamp', time() );
		}
		
		$this->state = new SessionState('ok');
		$this->stateCode[] = 10;
		return true;
	}
	
	/**
	 * Check validity of a session
	 * Note: a valid session with session='recoverable' still requires reauthenticating the user
	 * @return true is we have a session (state='ok'|'recoverable') or false otherwise
	 */
	public function check() {
		if ($this->started) { // Session already initialized
			$this->stateCode[] = 20;
			return ($this->state == 'ok' || $this->state == 'recoverable'); // return the same return value as before
		}
		
		if ( ! $this->fetchSessionId()) { // No sessionId found
			$this->state = new SessionState('none');
			$this->stateCode[] = 30;
			return false;
		}
		
		session_start();
		$this->started = true;
		$this->sessionId = session_id();
		$this->setHeaders();
		$this->claimSessionSuperGobal();

		$timestamp = $this->getPrivateData('timestamp');
		
		if (is_null($timestamp)) { // We have not no server-side timestamp information for this sessionId
			$this->state = new SessionState('failed');
			$this->stateCode[] = 40;
			return false;
		}
		
		if ($timestamp + ($this->config['timeTillReAuth'] * 60) < time() ) { // Session timed out
			if ($timestamp + ($this->config['timeToLive'] * 60) >= time() ) { // Session still within recoverable timeframe
				if ($this->getPrivateData('userLoginToken') === false) { // This is an anonymous session, auto recover
					$this->setPrivateData('timestamp', time());
					$this->state = new SessionState('ok');
					$this->stateCode[] = 50;
				} else { // This is a session bound to some user
					$this->state = new SessionState('recoverable');
					$this->stateCode[] = 60;
				}
				return true;
			}
			
			// Session is too old to be recovered
			$this->state = new SessionState('failed');
			$this->stateCode[] = 70;
			return false;
		}
		
		// The session is Ok
		$this->setPrivateData('timestamp', time());
		$this->state = new SessionState('ok');
		$this->stateCode[] = 80;
		return true;
	}
	
	/**
	 * Force a new sessionId to be regenerated and invalidate the old sessionId
	 * @return bool true on succes, false if no session is active
	 */
	public function forceNewSessionId() {
		if ( ! $this->started) { // Session already initialized
			return false;
		}
		$regenerateResult = session_regenerate_id(true);
		$this->sessionId = session_id();
		$this->setHeaders();
		return true;
	}
	
	/**
	 * Recover a session that needed rechecking the authentication of the user
	 * @return bool true on succes, false if the session was not in a recoverable state
	 */
	public function recover() {
		if ($this->state <> 'recoverable') {
			return false;
		}
		$this->setPrivateData('timestamp', time());
		$this->state = new SessionState('ok');
		$this->stateCode[] = 90;
		return true;
	}
	
	/**
	 * Destroy a session and unset all associated variables
	 * @return bool true on succes, false there was no session
	 */
	public function destroy() {
		if ($this->sessionId === false) {
			return false;
		}
		
		session_unset();
		if ($this->started) {
			session_destroy();
			$this->started = false;
		}
		
		header('Cache-Control:', true); // remove cache control header
		$this->cookies->delete(self::COOKIE_NAME);
		$this->sessionData = array();
		$this->state = new SessionState('none');
		return true;
	}
	
	/**
	 * Store some variable/value in the session so that we can use it in some subsequent request
	 * @param string $key the name used to store this value
	 * @param string $value the variable/value to store
	 * @return bool true
	 */
	public function setData($key, $value) {
		$this->sessionData['public'][$key] = serialize($value);
		return true;
	}
	
	/**
	 * Retrieve some variable/value stored in the session
	 * @param string $key the name used to store the value
	 * @return mixed null if the value could not be found, the originally stored variable otherwise
	 */
	public function getData($key) {
		if ( ! isset($this->sessionData['public'][$key])) {
			return null;
		}
		return unserialize($this->sessionData['public'][$key]);
	}
	
	/**
	 * Remove some registered variable
	 * @param string $key the name used to originally store the variable
	 * @return bool true
	 */
	public function deleteData($key) {
		unset($this->sessionData['public'][$key]);
		return true;
	}
	
	/**
	 * Set the session headers
	 * @return bool true
	 */
	protected function setHeaders() {
		$this->cookies->set(self::COOKIE_NAME, $this->sessionId, false, self::COOKIE_PATH);
		header('Cache-Control: private, max-age='.$this->config['timeToLive'].', pre-check='.$this->config['timeToLive'], true);
		header('Last-Modified: '.date('D, d M Y H:i:s').' GMT', true);
		return true;
	}
		
	/**
	 * Same as Session::setData, only this function is used to manage session related data
	 * @return bool true
	 */
	protected function setPrivateData($key, $value) {
		$this->sessionData['private'][$key] = serialize($value);
		return true;
	}
	/**
	 * Same as Session::getData, only this function is used to manage session related data
	 * @return mixed
	 */
	protected function getPrivateData($key) {
		if ( ! isset($this->sessionData['private'][$key])) {
			return false;
		}
		return unserialize($this->sessionData['private'][$key]);
	}
	/**
	 * Same as Session::deleteData, only this function is used to manage session related data
	 * @return bool true
	 */
	protected function deletePrivateData($key) {
		unset($this->sessionData['private'][$key]);
		return true;
	}
	
	/**
	 * Fetch the sessionId from either GET/POST or cookie
	 * @return bool true when a sessionId has been found, false otherwise
	 */
	private function fetchSessionId() {
		if (false === ($sessionId = $this->cookies->get(self::COOKIE_NAME))) {
			return false;
		}
		
		session_id($this->sanitizeSessionId($sessionId));
		return true;
	}
	
	/**
	 * Fetch the data stored un $_SESSION
	 * @return bool true
	 */
	private function claimSessionSuperGobal() {
		$this->sessionData = array_merge_recursive($_SESSION, $this->sessionData);
		$_SESSION = array('This variable is managed by the Session Object');
		return true;
	}
	
	/**
	 * Make sure the sessionId is of expected length and only contains expected characters
	 * If non white listed characters are encountered, they will be discarded. If the string, 
	 * after removing non-allowed characters, is not of the expected length, the string will be 
	 * padded with leading zeros
	 */
	private function sanitizeSessionId($sessionId) {
		if (self::HASH_BITS_PER_CHARACTER == 6) {
			$length = 22;
			$regEx = '/[^0-9A-Z\\-,]/i';
		} elseif (self::HASH_BITS_PER_CHARACTER == 5) {
			$length = 26;
			$regEx = '/[^0-9a-v]/';
		} else { // self::HASH_BITS_PER_CHARACTER == 4
			$length = 32;
			$regEx = '/[^0-9a-f]/';
		}
		return str_pad(substr(preg_replace($regEx, '', $sessionId), 0, $length), $length, 0, STR_PAD_LEFT);
	}
	
	/**
	 * Store session variables & unset object references
	 */
	public function __destruct() {
		$_SESSION = $this->sessionData;
		session_write_close();
		
		$this->config = null;
		$this->cookies = null;
		$this->userId = null;
	}
}
